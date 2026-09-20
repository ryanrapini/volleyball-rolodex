<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\BulkAnswersRequest;
use App\Http\Requests\PersonRequest;
use App\Models\Category;
use App\Models\CategoryOption;
use App\Models\Person;
use App\Support\PersonAnswers;
use App\Support\PersonPhotos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    use AuthorizesRequests;

    /** Values a yes/no chip can send. */
    private const YES = 'yes';

    private const NO = 'no';

    /**
     * Home screen for the whole app: search the rolodex and scroll the list.
     */
    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));
        $categories = $request->user()->categories()->with('options')->get();
        $filters = $this->readFilters($request, $categories);

        $people = $request->user()->people()
            ->when($term !== '', fn (Builder $query) => $query->where(
                fn (Builder $search) => $this->applySearch($search, $term),
            ))
            ->when($filters !== [], fn (Builder $query) => $this->applyFilters($query, $filters))
            ->with(['categoryValues.category', 'categoryValues.option'])
            ->orderBy('name')
            ->paginate(60)
            ->withQueryString()
            ->through(fn (Person $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'email' => $person->email,
                'photo_url' => PersonPhotos::url($person->photo_path),
                'notes_excerpt' => Str::limit((string) $person->notes, 120),
                'tags' => $person->categoryTags(),
            ]);

        return Inertia::render('People/Index', [
            'people' => $people,
            'filters' => [
                'q' => $term,
                'categories' => array_map(
                    fn (array $values): array => array_values($values),
                    $filters,
                ),
            ],
            'filterOptions' => $this->filterOptions($categories, $filters),
        ]);
    }

    public function create(Request $request): Response
    {
        $categories = $request->user()->categories()->with('options')->get();

        return Inertia::render('People/Create', [
            'categories' => $this->presentCategories($categories),
            'answers' => PersonAnswers::blank($categories),
        ]);
    }

    public function store(PersonRequest $request): RedirectResponse
    {
        $person = $request->user()->people()->create(
            $request->safe()->except(['photo', 'remove_photo', 'answers']),
        );

        if ($request->hasFile('photo')) {
            PersonPhotos::store($person, $request->file('photo'));
        }

        PersonAnswers::sync($person, $request->answers(), $request->categories());

        return redirect()
            ->route('people.show', $person)
            ->with('status', 'Added '.$person->name.' to your rolodex.');
    }

    public function show(Person $person): Response
    {
        $this->authorize('view', $person);

        $person->load(['categoryValues.category', 'categoryValues.option']);

        return Inertia::render('People/Show', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'email' => $person->email,
                'notes' => $person->notes,
                'photo_url' => PersonPhotos::url($person->photo_path),
                'answer_groups' => $person->answerGroups(),
            ],
        ]);
    }

    public function edit(Request $request, Person $person): Response
    {
        $this->authorize('update', $person);

        $categories = $request->user()->categories()->with('options')->get();

        return Inertia::render('People/Edit', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'email' => $person->email,
                'notes' => $person->notes,
                'photo_url' => PersonPhotos::url($person->photo_path),
            ],
            'categories' => $this->presentCategories($categories),
            'answers' => PersonAnswers::forPerson($person, $categories),
        ]);
    }

    public function update(PersonRequest $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $this->applyUpdate($request, $person);

        return redirect()
            ->route('people.show', $person)
            ->with('status', 'Saved '.$person->name.'.');
    }

    /**
     * Apply the same answers to several people from the list.
     *
     * Pick-any categories are merged into whatever each person already has, so
     * this adds rather than replaces. Anything the owner did not set is left
     * exactly as it was — a bulk edit should never wipe a value by omission.
     */
    public function bulkAnswers(BulkAnswersRequest $request): JsonResponse
    {
        $categories = $request->categories();
        $answers = $request->answers();

        $people = $request->user()->people()
            ->whereIn('id', $request->people())
            ->get();

        $updated = 0;

        foreach ($people as $person) {
            $changes = [];

            foreach ($answers as $categoryId => $answer) {
                $category = $categories->get($categoryId);

                if (! $category instanceof Category) {
                    continue;
                }

                if ($category->type === CategoryType::Multiple) {
                    // An empty selection means "leave this category alone", not
                    // "clear it" — otherwise every untouched row counts as a change.
                    if ($answer['option_ids'] === []) {
                        continue;
                    }

                    $existing = $person->categoryValues()
                        ->where('category_id', $categoryId)
                        ->pluck('category_option_id')
                        ->filter()
                        ->all();

                    $merged = array_values(array_unique([...$existing, ...$answer['option_ids']]));

                    $changes[$categoryId] = ['value' => null, 'option_id' => null, 'option_ids' => $merged];

                    continue;
                }

                $chosen = $category->type === CategoryType::Boolean
                    ? $answer['value'] !== null
                    : $answer['option_id'] !== null;

                if ($chosen) {
                    $changes[$categoryId] = $answer;
                }
            }

            if ($changes === []) {
                continue;
            }

            PersonAnswers::sync($person, $changes, $categories);
            $updated++;
        }

        return response()->json([
            'selected' => $people->count(),
            'updated' => $updated,
        ]);
    }

    /**
     * What the quick-edit popup needs: the full record (the list only carries an
     * excerpt of the notes), the owner's categories, and this person's answers.
     */
    public function quickEdit(Request $request, Person $person): JsonResponse
    {
        $this->authorize('update', $person);

        $categories = $request->user()->categories()->with('options')->get();

        return response()->json([
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'email' => $person->email,
                'notes' => $person->notes,
                'photo_url' => PersonPhotos::url($person->photo_path),
            ],
            'categories' => $this->presentCategories($categories),
            'answers' => PersonAnswers::forPerson($person, $categories),
        ]);
    }

    /**
     * Save from the quick-edit popup and hand back the card's data, so the list
     * can update without a round trip through the detail page.
     */
    public function quickUpdate(PersonRequest $request, Person $person): JsonResponse
    {
        $this->authorize('update', $person);

        $this->applyUpdate($request, $person);

        $person->load(['categoryValues.category', 'categoryValues.option']);

        return response()->json([
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'email' => $person->email,
                'photo_url' => PersonPhotos::url($person->photo_path),
                'notes_excerpt' => Str::limit((string) $person->notes, 120),
                'tags' => $person->categoryTags(),
            ],
        ]);
    }

    /**
     * Everything an edit does, whichever door it came through.
     */
    private function applyUpdate(PersonRequest $request, Person $person): void
    {
        $person->update($request->safe()->except(['photo', 'remove_photo', 'answers']));

        if ($request->hasFile('photo')) {
            PersonPhotos::store($person, $request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            PersonPhotos::forget($person->photo_path);
            $person->photo_path = null;
            $person->save();
        }

        PersonAnswers::sync($person, $request->answers(), $request->categories());
    }

    public function destroy(Person $person): RedirectResponse
    {
        $this->authorize('delete', $person);

        $name = $person->name;
        $person->delete();

        return redirect()
            ->route('people.index')
            ->with('status', 'Removed '.$name.'.');
    }

    /**
     * Read the chip selections out of the query string, keeping only ids and
     * values this user's categories actually offer. Ignoring junk here means a
     * hand-edited URL can never widen or crash a query.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<string, array<int, string>>
     */
    private function readFilters(Request $request, Collection $categories): array
    {
        $submitted = $request->query('f');

        if (! is_array($submitted)) {
            return [];
        }

        $filters = [];

        foreach ($submitted as $categoryId => $values) {
            $category = $categories->firstWhere('id', (string) $categoryId);

            if (! $category instanceof Category) {
                continue;
            }

            $allowed = $category->type === CategoryType::Boolean
                ? [self::YES, self::NO]
                : $category->options->pluck('id')->all();

            $kept = array_values(array_unique(array_filter(
                array_map('trim', explode(',', is_array($values) ? implode(',', $values) : (string) $values)),
                fn (string $value): bool => in_array($value, $allowed, true),
            )));

            if ($kept !== []) {
                $filters[$category->getKey()] = $kept;
            }
        }

        return $filters;
    }

    /**
     * Each selected category narrows the list; within one category, matching any
     * of the chosen chips is enough.
     *
     * @param  Builder<Person>  $query
     * @param  array<string, array<int, string>>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $categoryId => $values) {
            $yesNo = array_intersect($values, [self::YES, self::NO]);
            $optionIds = array_diff($values, [self::YES, self::NO]);

            $query->where(function (Builder $match) use ($categoryId, $yesNo, $optionIds): void {
                foreach ($yesNo as $value) {
                    $match->orWhereHas('categoryValues', fn (Builder $value_) => $value_
                        ->where('category_id', $categoryId)
                        ->where('value', $value === self::YES));
                }

                if ($optionIds !== []) {
                    $match->orWhereHas('categoryValues', fn (Builder $value_) => $value_
                        ->where('category_id', $categoryId)
                        ->whereIn('category_option_id', array_values($optionIds)));
                }
            });
        }
    }

    /**
     * The chips to render, with their current state.
     *
     * @param  Collection<int, Category>  $categories
     * @param  array<string, array<int, string>>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function filterOptions(Collection $categories, array $filters): array
    {
        return $categories
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'chips' => $category->type === CategoryType::Boolean
                    ? [
                        ['value' => self::YES, 'label' => 'Yes'],
                        ['value' => self::NO, 'label' => 'No'],
                    ]
                    : $category->options
                        ->map(fn (CategoryOption $option): array => [
                            'value' => $option->id,
                            'label' => $option->label,
                        ])
                        ->values()
                        ->all(),
                'selected' => $filters[$category->getKey()] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array<int, array<string, mixed>>
     */
    private function presentCategories($categories): array
    {
        return $categories
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'options' => $category->options
                    ->map(fn (CategoryOption $option): array => [
                        'id' => $option->id,
                        'label' => $option->label,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Match the term against every text field on the record.
     *
     * The term is escaped rather than stripped, so a literal "%" or "_" in the
     * box matches that character instead of turning into a wildcard. SQLite and
     * PostgreSQL both honour the ESCAPE clause used here.
     *
     * @param  Builder<Person>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($term));
        $needle = '%'.$escaped.'%';

        $query->whereRaw("lower(name) like ? escape '\\'", [$needle]);

        foreach (['phone', 'email', 'notes'] as $column) {
            $query->orWhereRaw("lower(".$column.") like ? escape '\\'", [$needle]);
        }

        // Dialling digits should find a number however it was punctuated.
        $digits = preg_replace('/\D+/', '', $term);

        if (is_string($digits) && $digits !== '') {
            $query->orWhereRaw('phone_digits like ?', ['%'.$digits.'%']);
        }
    }
}
