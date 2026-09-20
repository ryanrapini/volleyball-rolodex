<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

class PersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
            ...$this->answerRules(),
        ];
    }

    /**
     * The rules for the answer payload, shared with the bulk editor so both
     * doors validate category data identically.
     *
     * @return array<string, mixed>
     */
    protected function answerRules(): array
    {
        return [
            // Answers arrive as a list so an entry always carries its category
            // id, even when every field inside it is empty.
            'answers' => ['array'],
            'answers.*.category_id' => ['required', 'uuid'],
            'answers.*.value' => ['nullable', 'boolean'],
            'answers.*.option_id' => ['nullable', 'uuid'],
            'answers.*.option_ids' => ['array'],
            'answers.*.option_ids.*' => ['uuid'],
        ];
    }

    /**
     * Categories and choices are supplied by the browser, so check every id in
     * the payload really belongs to this user before it is written.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $categories = $this->categories();
            $seen = [];

            foreach ((array) $this->input('answers', []) as $index => $raw) {
                $answer = (array) $raw;
                $categoryId = (string) ($answer['category_id'] ?? '');
                $category = $categories->get($categoryId);

                if (! $category instanceof Category) {
                    $validator->errors()->add("answers.{$index}.category_id", 'That category is not in your rolodex.');

                    continue;
                }

                if (isset($seen[$categoryId])) {
                    $validator->errors()->add("answers.{$index}.category_id", $category->name.' was sent twice.');

                    continue;
                }

                $seen[$categoryId] = true;

                $optionIds = $this->optionIds($answer);

                if ($category->type === CategoryType::Boolean) {
                    if ($optionIds !== []) {
                        $validator->errors()->add("answers.{$index}", $category->name.' is a yes / no category.');
                    }

                    continue;
                }

                if (($answer['value'] ?? null) !== null) {
                    $validator->errors()->add("answers.{$index}", $category->name.' takes a choice, not a yes / no.');

                    continue;
                }

                $owned = $category->options->pluck('id')->all();

                foreach ($optionIds as $optionId) {
                    if (! in_array($optionId, $owned, true)) {
                        $validator->errors()->add("answers.{$index}", 'That choice is not part of '.$category->name.'.');
                    }
                }

                if ($category->type === CategoryType::Single && count($optionIds) > 1) {
                    $validator->errors()->add("answers.{$index}", $category->name.' takes only one choice.');
                }
            }
        });
    }

    /**
     * The categories this submission is allowed to answer.
     *
     * @return Collection<string, Category>
     */
    public function categories(): Collection
    {
        return $this->user()->categories()->with('options')->get()->keyBy('id');
    }

    /**
     * Answers keyed by category id, in the shape PersonAnswers::sync expects.
     *
     * @return array<string, array{value: bool|null, option_id: string|null, option_ids: array<int, string>}>
     */
    public function answers(): array
    {
        $answers = [];

        foreach ((array) $this->input('answers', []) as $raw) {
            $answer = (array) $raw;
            $categoryId = (string) ($answer['category_id'] ?? '');

            if ($categoryId === '') {
                continue;
            }

            $value = $answer['value'] ?? null;

            $answers[$categoryId] = [
                'value' => $this->booleanValue($value),
                'option_id' => isset($answer['option_id']) && $answer['option_id'] !== ''
                    ? (string) $answer['option_id']
                    : null,
                'option_ids' => array_map('strval', (array) ($answer['option_ids'] ?? [])),
            ];
        }

        return $answers;
    }

    /**
     * An unset answer must stay null rather than collapsing to false, or
     * clearing a yes/no would silently record a "no" instead.
     */
    private function booleanValue(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Every option id in an answer, whether it arrived in the single or the
     * multiple slot.
     *
     * @param  array<string, mixed>  $answer
     * @return array<int, string>
     */
    private function optionIds(array $answer): array
    {
        $ids = array_map('strval', (array) ($answer['option_ids'] ?? []));

        if (isset($answer['option_id']) && $answer['option_id'] !== '') {
            $ids[] = (string) $answer['option_id'];
        }

        return array_values(array_unique($ids));
    }
}
