<?php

namespace App\Support\Ai;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Person;
use App\Models\User;
use App\Support\DuplicatePeople;
use App\Support\PersonAnswers;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * The rolodex assistant: an OpenAI chat loop that can add and edit people.
 *
 * The tool schemas and the system prompt are both derived from the signed-in
 * user's own categories, so the model is told exactly which questions this
 * rolodex asks and which answers are legal.
 */
class RolodexAssistant
{
    private const MAX_STEPS = 6;

    public function __construct(private readonly User $user) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{reply: string, actions: array<int, array<string, mixed>>}
     */
    public function reply(array $messages): array
    {
        // Keyed by id: PersonAnswers::sync looks categories up by id.
        $categories = $this->user->categories()->with('options')->get()->keyBy('id');

        $conversation = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($categories)]],
            $messages,
        );

        $actions = [];

        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            $payload = [
                'model' => (string) config('services.openai.model'),
                'messages' => $conversation,
                'tools' => $this->tools(),
                'tool_choice' => 'auto',
            ];

            $response = $this->client()->post('/chat/completions', $payload)->throw()->json();

            /** @var array<string, mixed> $message */
            $message = $response['choices'][0]['message'] ?? [];
            $toolCalls = $message['tool_calls'] ?? [];

            if (! is_array($toolCalls) || $toolCalls === []) {
                return [
                    'reply' => trim((string) ($message['content'] ?? '')) ?: 'Done.',
                    'actions' => $actions,
                ];
            }

            $conversation[] = $message;

            foreach ($toolCalls as $call) {
                $conversation[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'] ?? '',
                    'content' => json_encode($this->runTool($call, $categories, $actions)),
                ];
            }
        }

        return [
            'reply' => 'That needed more steps than I am allowed. Try asking for one person at a time.',
            'actions' => $actions,
        ];
    }

    /**
     * Whether the app has somewhere to send the request.
     */
    public static function isConfigured(): bool
    {
        return trim((string) config('services.openai.key')) !== '';
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function systemPrompt(Collection $categories): string
    {
        $lines = [];

        foreach ($categories as $category) {
            $choices = match ($category->type) {
                CategoryType::Boolean => 'answer with "Yes" or "No"',
                CategoryType::Single => 'pick exactly one of: '.$category->options->pluck('label')->implode(', '),
                CategoryType::Multiple => 'pick any of: '.$category->options->pluck('label')->implode(', '),
            };

            $lines[] = '- '.$category->name.' ('.$choices.')';
        }

        $categoryBlock = $lines === []
            ? 'This rolodex has no categories yet. You can still add people by name.'
            : implode("\n", $lines);

        return <<<PROMPT
        You fill in a private volleyball rolodex owned by {$this->user->name}. You are not a
        general assistant: every reply either does something to the rolodex or explains why
        you could not.

        The owner's categories, which are the only questions this rolodex asks:
        {$categoryBlock}

        Rules:
        - Use the tools. Never claim to have saved someone without a tool result saying so.
        - Always check for duplicates before adding someone: call find_people with the name
          first, and treat what it returns as the owner's existing people. If create_person
          comes back with possible_duplicates, do not argue with it — ask the owner whether
          to update one of those people or whether this is genuinely a different person, and
          only retry with confirm_new=true once they have confirmed it is different.
        - A name is the only thing required. Add whatever else the owner said and leave the
          rest unset rather than inventing details.
        - Only use the category names and choices listed above, verbatim. If the owner
          describes something that does not fit, say so instead of forcing it.
        - Call find_people first when the owner refers to someone who may already be in the
          rolodex, and prefer update_person over create_person for those people.
        - Never delete anyone. If asked to, say the owner has to do that themselves.
        - Keep replies to one or two short sentences. The owner can see the cards.
        PROMPT;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tools(): array
    {
        $answerSchema = [
            'type' => 'array',
            'description' => 'Answers for this person, using the category names and choices from the system prompt.',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'category' => [
                        'type' => 'string',
                        'description' => 'The exact category name.',
                    ],
                    'answer' => [
                        'type' => ['string', 'array'],
                        'items' => ['type' => 'string'],
                        'description' => 'Yes/No for a yes-no category, a choice label for pick-one, or several labels for pick-any.',
                    ],
                ],
                'required' => ['category', 'answer'],
            ],
        ];

        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'find_people',
                    'description' => 'Check for duplicates: look someone up in the rolodex before adding or editing them. Always call this before create_person.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'A name, or part of one.'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_person',
                    'description' => 'Add a new person to the rolodex. Refuses and returns possible_duplicates if the name looks like someone already stored; only retry with confirm_new=true after the owner confirms it is a different person.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'First and last name.'],
                            'phone' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                            'notes' => ['type' => 'string', 'description' => 'Anything else worth remembering.'],
                            'confirm_new' => [
                                'type' => 'boolean',
                                'description' => 'Set true only when the owner has confirmed this is a different person from the possible_duplicates that were returned.',
                            ],
                            'answers' => $answerSchema,
                        ],
                        'required' => ['name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_person',
                    'description' => 'Change someone already in the rolodex. Only send the fields that change.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'person' => ['type' => 'string', 'description' => 'The name of the person to change.'],
                            'phone' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                            'notes' => ['type' => 'string'],
                            'answers' => $answerSchema,
                        ],
                        'required' => ['person'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $call
     * @param  Collection<int, Category>  $categories
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<string, mixed>
     */
    private function runTool(array $call, Collection $categories, array &$actions): array
    {
        $name = (string) ($call['function']['name'] ?? '');
        $arguments = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
        $input = is_array($arguments) ? $arguments : [];

        return match ($name) {
            'find_people' => $this->findPeople($input),
            'create_person' => $this->createPerson($input, $categories, $actions),
            'update_person' => $this->updatePerson($input, $categories, $actions),
            default => ['error' => 'Unknown tool: '.$name],
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function findPeople(array $input): array
    {
        $query = trim((string) ($input['query'] ?? ''));

        $people = $this->user->people()
            ->with(['categoryValues.category', 'categoryValues.option'])
            ->when($query !== '', fn ($builder) => $builder->where('name', 'like', '%'.$query.'%'))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Person $person): array => [
                'name' => $person->name,
                'tags' => $person->categoryTags(10),
            ])
            ->all();

        return ['people' => $people];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  Collection<int, Category>  $categories
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<string, mixed>
     */
    private function createPerson(array $input, Collection $categories, array &$actions): array
    {
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            return ['error' => 'A name is required.'];
        }

        if (! ($input['confirm_new'] ?? false)) {
            $duplicates = $this->duplicateCandidates($name);

            if ($duplicates->isNotEmpty()) {
                return [
                    'error' => $name.' looks like someone already in the rolodex. Ask the owner whether to update one of these people, or whether this really is a different person.',
                    'possible_duplicates' => $duplicates->map(fn (Person $person): array => [
                        'name' => $person->name,
                        'phone' => $person->phone,
                        'tags' => $person->categoryTags(6),
                    ])->all(),
                    'retry' => 'Only call create_person again with confirm_new=true once the owner has said it is a different person.',
                ];
            }
        }

        $attributes = $this->contactAttributes($input);
        $attributes['name'] = $name;

        $person = $this->user->people()->create($attributes);

        [$answers, $warnings] = $this->answersFromTool($input, $categories);
        PersonAnswers::sync($person, $answers, $categories);

        $actions[] = ['type' => 'created', 'name' => $person->name, 'id' => $person->getKey()];

        return [
            'created' => $person->name,
            'recorded' => $this->summariseAnswers($answers, $categories),
            'warnings' => $warnings,
        ];
    }

    /**
     * People whose name looks like the one about to be added.
     *
     * A personal rolodex is small, so comparing every name is both simpler and
     * more reliable than a SQL prefilter — a prefilter on the first word would
     * let a typo in that word slip straight past the check.
     *
     * @return Collection<int, Person>
     */
    private function duplicateCandidates(string $name): Collection
    {
        // The same rules the manual form uses, so the two cannot disagree about
        // what "the same person twice" means.
        return (new DuplicatePeople($this->user))
            ->byName($name)
            ->load(['categoryValues.category', 'categoryValues.option']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  Collection<int, Category>  $categories
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<string, mixed>
     */
    private function updatePerson(array $input, Collection $categories, array &$actions): array
    {
        $lookup = trim((string) ($input['person'] ?? ''));

        if ($lookup === '') {
            return ['error' => 'Say who to update.'];
        }

        $matches = $this->user->people()
            ->where('name', 'like', '%'.$lookup.'%')
            ->limit(5)
            ->get();

        if ($matches->isEmpty()) {
            return ['error' => 'Nobody called '.$lookup.' is in the rolodex.'];
        }

        if ($matches->count() > 1) {
            return [
                'error' => 'That matches more than one person. Ask the owner which one.',
                'matches' => $matches->pluck('name')->all(),
            ];
        }

        /** @var Person $person */
        $person = $matches->first();
        $person->update($this->contactAttributes($input));

        [$answers, $warnings] = $this->answersFromTool($input, $categories);
        PersonAnswers::sync($person, $answers, $categories);

        $actions[] = ['type' => 'updated', 'name' => $person->name, 'id' => $person->getKey()];

        return [
            'updated' => $person->name,
            'recorded' => $this->summariseAnswers($answers, $categories),
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>
     */
    private function contactAttributes(array $input): array
    {
        $attributes = [];

        foreach (['phone', 'email', 'notes'] as $field) {
            if (isset($input[$field]) && is_string($input[$field]) && trim($input[$field]) !== '') {
                $attributes[$field] = trim($input[$field]);
            }
        }

        return $attributes;
    }

    /**
     * Turn the model's answer list into the shape PersonAnswers::sync expects,
     * collecting anything it got wrong so the model can correct itself.
     *
     * @param  array<string, mixed>  $input
     * @param  Collection<int, Category>  $categories
     * @return array{0: array<string, array<string, mixed>>, 1: array<int, string>}
     */
    private function answersFromTool(array $input, Collection $categories): array
    {
        $answers = [];
        $warnings = [];

        foreach ((array) ($input['answers'] ?? []) as $entry) {
            $entry = (array) $entry;
            $wanted = mb_strtolower(trim((string) ($entry['category'] ?? '')));

            $category = $categories->first(
                fn (Category $candidate): bool => mb_strtolower($candidate->name) === $wanted,
            );

            if (! $category instanceof Category) {
                $warnings[] = 'There is no category called "'.($entry['category'] ?? '').'".';

                continue;
            }

            if ($category->type === CategoryType::Boolean) {
                $answers[$category->getKey()] = [
                    'value' => $this->yesNo($entry['answer'] ?? null),
                    'option_id' => null,
                    'option_ids' => [],
                ];

                continue;
            }

            $labels = array_values(array_filter(
                array_map(
                    fn (mixed $label): string => trim((string) $label),
                    is_array($entry['answer'] ?? null) ? $entry['answer'] : [$entry['answer'] ?? ''],
                ),
                fn (string $label): bool => $label !== '',
            ));

            $optionIds = [];

            foreach ($labels as $label) {
                $option = $category->options->first(
                    fn ($candidate): bool => mb_strtolower($candidate->label) === mb_strtolower($label),
                );

                if ($option === null) {
                    $warnings[] = '"'.$label.'" is not a choice of "'.$category->name.'".';

                    continue;
                }

                $optionIds[] = $option->getKey();
            }

            $answers[$category->getKey()] = $category->type === CategoryType::Single
                ? ['value' => null, 'option_id' => $optionIds[0] ?? null, 'option_ids' => []]
                : ['value' => null, 'option_id' => null, 'option_ids' => $optionIds];
        }

        return [$answers, $warnings];
    }

    private function yesNo(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $text = mb_strtolower(trim((string) $value));

        return match ($text) {
            'yes', 'y', 'true', '1' => true,
            'no', 'n', 'false', '0' => false,
            default => null,
        };
    }

    /**
     * Read back what was recorded, so the model can confirm it accurately.
     *
     * @param  array<string, array<string, mixed>>  $answers
     * @param  Collection<int, Category>  $categories
     * @return array<int, string>
     */
    private function summariseAnswers(array $answers, Collection $categories): array
    {
        $summary = [];

        foreach ($answers as $categoryId => $answer) {
            $category = $categories->firstWhere('id', $categoryId);

            if (! $category instanceof Category) {
                continue;
            }

            if ($category->type === CategoryType::Boolean) {
                if ($answer['value'] !== null) {
                    $summary[] = $category->name.': '.($answer['value'] ? 'Yes' : 'No');
                }

                continue;
            }

            $ids = array_filter([$answer['option_id'], ...$answer['option_ids']]);
            $labels = $category->options
                ->filter(fn ($option): bool => in_array($option->getKey(), $ids, true))
                ->pluck('label')
                ->all();

            if ($labels !== []) {
                $summary[] = $category->name.': '.implode(', ', $labels);
            }
        }

        return $summary;
    }

    private function client(): PendingRequest
    {
        $key = trim((string) config('services.openai.key'));

        if ($key === '') {
            throw new RuntimeException('No OpenAI API key is configured.');
        }

        return Http::baseUrl(rtrim((string) config('services.openai.base_url'), '/'))
            ->withToken($key)
            ->acceptJson()
            ->timeout(90);
    }
}
