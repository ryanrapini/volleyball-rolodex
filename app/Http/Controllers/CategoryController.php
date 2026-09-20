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
        ]);

        $this->syncOptions($category, $request->options());

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
        ]);

        $this->syncOptions($category, $request->options());

        return redirect()
            ->route('categories.index')
            ->with('status', 'Saved the '.$category->name.' category.');
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
     * @param  array<int, string>  $labels
     */
    private function syncOptions(Category $category, array $labels): void
    {
        if (! $category->type->hasOptions()) {
            $category->options()->delete();

            return;
        }

        $existing = $category->options()->get()->keyBy('label');
        $kept = [];

        foreach ($labels as $position => $label) {
            $option = $existing->get($label);

            if ($option instanceof CategoryOption) {
                if ($option->position !== $position) {
                    $option->update(['position' => $position]);
                }

                $kept[] = $option->getKey();

                continue;
            }

            $kept[] = $category->options()
                ->create(['label' => $label, 'position' => $position])
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
            'options' => $category->options
                ->map(fn (CategoryOption $option): array => [
                    'id' => $option->id,
                    'label' => $option->label,
                ])
                ->values()
                ->all(),
        ];
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
