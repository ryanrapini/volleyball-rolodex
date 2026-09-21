<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\CategoryOption;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $categories = $request->user()->categories()
            ->with('options')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => $this->present($category))
            ->all();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Categories/Create', [
            'types' => $this->typeChoices(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = $request->user()->categories()->create([
            'name' => (string) $request->string('name'),
            'type' => $request->type(),
            'position' => ((int) $request->user()->categories()->max('position')) + 1,
            'show_on_card' => $request->boolean('show_on_card', true),
            'show_name_on_card' => $request->boolean('show_name_on_card', true),
            'colour' => $request->colour(),
        ]);

        $this->syncOptions($category, $request->choices());

        $category->update([
            'default_filter' => $this->defaultFilter($category, (array) $request->input('default_filter', [])),
        ]);

        return redirect()
            ->route('categories.index')
            ->with('status', 'Added the '.$category->name.' category.');
    }

    public function edit(Category $category): Response
    {
        $this->authorize('update', $category);

        return Inertia::render('Categories/Edit', [
            'category' => $this->present($category->load('options')),
            'types' => $this->typeChoices(),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update([
            'name' => (string) $request->string('name'),
            'type' => $request->type(),
            'show_on_card' => $request->boolean('show_on_card', true),
            'show_name_on_card' => $request->boolean('show_name_on_card', true),
            'colour' => $request->colour(),
        ]);

        $this->syncOptions($category, $request->choices());

        $category->update([
            'default_filter' => $this->defaultFilter($category, (array) $request->input('default_filter', [])),
        ]);

        return redirect()
            ->route('categories.index')
            ->with('status', 'Saved the '.$category->name.' category.');
    }

    /**
     * Set — or clear — the filter state this category's list opens in, straight
     * from the filter control the owner is already using, so a filter they like
     * does not have to be rebuilt in the category settings.
     */
    public function storeDefaultFilter(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        // These arrive already in the stored shape: choice ids, or yes / no, or
        // "unset" for nobody-has-answered.
        $values = array_values(array_unique(array_map('strval', (array) $request->input('values', []))));

        $allowed = array_merge(
            $category->type === CategoryType::Boolean
                ? ['yes', 'no']
                : $category->options()->pluck('id')->all(),
            ['unset'],
        );

        if (array_diff($values, $allowed) !== []) {
            return back()->with('error', 'That is not an answer '.$category->name.' has.');
        }

        if ($category->type === CategoryType::Single && count(array_diff($values, ['unset'])) > 1) {
            return back()->with('error', $category->name.' takes one answer, so it can only default to one.');
        }

        $category->update(['default_filter' => $values]);

        return back()->with('status', $values === []
            ? $category->name.' opens with no filter now.'
            : $category->name.' opens with '.$this->labelsFor($category, $values).'.');
    }

    /**
     * @param  array<int, string>  $values
     */
    private function labelsFor(Category $category, array $values): string
    {
        if ($category->type === CategoryType::Boolean) {
            return implode(' and ', array_map(
                fn (string $value): string => $value === 'unset' ? 'nobody has answered' : $value,
                $values,
            ));
        }

        $labels = $category->options()->whereIn('id', $values)->pluck('label')->all();

        if (in_array('unset', $values, true)) {
            $labels[] = 'nobody has answered';
        }

        return implode(', ', $labels);
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $name = $category->name;
        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('status', 'Removed the '.$name.' category.');
    }

    /**
     * Reconcile the choice list in place: existing choices keep their identity
     * (and any values already recorded against them), new ones are added, and
     * dropped ones are removed.
     *
     * @param  array<int, array{label: string, colour: ?string}>  $choices
     */
    private function syncOptions(Category $category, array $choices): void
    {
        if (! $category->type->hasOptions()) {
            $category->options()->delete();

            return;
        }

        $existing = $category->options()->get()->keyBy('label');
        $kept = [];

        foreach ($choices as $position => $choice) {
            $label = $choice['label'];
            $colour = $choice['colour'];
            $option = $existing->get($label);

            if ($option instanceof CategoryOption) {
                if ($option->position !== $position || $option->colour !== $colour) {
                    $option->update(['position' => $position, 'colour' => $colour]);
                }

                $kept[] = $option->getKey();

                continue;
            }

            $kept[] = $category->options()
                ->create(['label' => $label, 'position' => $position, 'colour' => $colour])
                ->getKey();
        }

        $category->options()->whereNotIn('id', $kept)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'type' => $category->type->value,
            'type_label' => $category->type->label(),
            'has_options' => $category->type->hasOptions(),
            'show_on_card' => (bool) $category->show_on_card,
            'show_name_on_card' => (bool) $category->show_name_on_card,
            'colour' => $category->colour,
            'options' => $category->options
                ->map(fn (CategoryOption $option): array => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'colour' => $option->colour,
                ])
                ->values()
                ->all(),
            // Colours line up with the choices, so the form can show them beside
            // each one.
            'option_colours' => $category->options->pluck('colour')->values()->all(),
            'default_filter' => $this->defaultFilterPositions($category),
        ];
    }

    /**
     * The default filter arrives as positions in the choice list the form is
     * submitting, because a category being created has no choice ids yet. Turn
     * those into ids now that the choices exist.
     *
     * @param  array<int, mixed>  $submitted
     * @return array<int, string>
     */
    private function defaultFilter(Category $category, array $submitted): array
    {
        $submitted = array_map('strval', $submitted);

        // "Unset" travels as itself: it is not one of the answers.
        $unset = in_array('unset', $submitted, true);

        if (! $category->type->hasOptions()) {
            $values = array_values(array_intersect($submitted, ['yes', 'no']));
        } else {
            $ids = $category->options()->pluck('id')->all();
            $values = [];

            foreach ($submitted as $position) {
                $id = is_numeric($position) ? ($ids[(int) $position] ?? null) : null;

                if ($id !== null) {
                    $values[] = $id;
                }
            }

            $values = array_values(array_unique($values));
        }

        return $unset ? [...$values, 'unset'] : $values;
    }

    /**
     * The stored filter turned back into choice positions, for the form.
     *
     * @return array<int, int|string>
     */
    private function defaultFilterPositions(Category $category): array
    {
        $stored = $category->default_filter ?? [];

        if ($category->type === CategoryType::Boolean) {
            return array_values($stored);
        }

        $ids = $category->options->pluck('id')->all();
        $positions = [];

        foreach ($stored as $value) {
            if ($value === 'unset') {
                $positions[] = 'unset';

                continue;
            }

            $index = array_search($value, $ids, true);

            if ($index !== false) {
                $positions[] = $index;
            }
        }

        return $positions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function typeChoices(): array
    {
        return array_map(fn (CategoryType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'hint' => $type->hint(),
            'has_options' => $type->hasOptions(),
        ], CategoryType::cases());
    }
}
