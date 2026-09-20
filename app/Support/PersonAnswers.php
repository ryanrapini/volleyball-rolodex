<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Person;
use Illuminate\Support\Collection;

/**
 * Reads and writes a person's answers to their owner's categories.
 *
 * The shape handed to and from the browser is uniform so the form can stay
 * dumb: every category always has all three keys, and only the one that matches
 * the category's type is ever filled in.
 */
class PersonAnswers
{
    /**
     * Every category, with nothing answered.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<string, array{value: bool|null, option_id: string|null, option_ids: array<int, string>}>
     */
    public static function blank(Collection $categories): array
    {
        $answers = [];

        foreach ($categories as $category) {
            $answers[$category->getKey()] = self::emptyAnswer();
        }

        return $answers;
    }

    /**
     * The person's current answers in the same uniform shape.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<string, array{value: bool|null, option_id: string|null, option_ids: array<int, string>}>
     */
    public static function forPerson(Person $person, Collection $categories): array
    {
        $answers = self::blank($categories);
        $values = $person->categoryValues()->get();

        foreach ($categories as $category) {
            $key = $category->getKey();
            $mine = $values->where('category_id', $key);

            if ($category->type === CategoryType::Boolean) {
                $yes = $mine->firstWhere('value', true);
                $no = $mine->firstWhere('value', false);

                $answers[$key]['value'] = $yes !== null ? true : ($no !== null ? false : null);

                continue;
            }

            $optionIds = $mine->pluck('category_option_id')->filter()->values()->all();

            if ($category->type === CategoryType::Single) {
                $answers[$key]['option_id'] = $optionIds[0] ?? null;

                continue;
            }

            $answers[$key]['option_ids'] = $optionIds;
        }

        return $answers;
    }

    /**
     * Replace the person's answers for every category present in the payload.
     * Categories that aren't in the payload are left alone.
     *
     * @param  array<string, array<string, mixed>>  $answers
     * @param  Collection<int, Category>  $categories
     */
    public static function sync(Person $person, array $answers, Collection $categories): void
    {
        $person->categoryValues()
            ->whereIn('category_id', array_keys($answers))
            ->delete();

        foreach ($answers as $categoryId => $answer) {
            $category = $categories->get($categoryId);

            if (! $category instanceof Category) {
                continue;
            }

            foreach (self::rowsFor($category, $answer) as $row) {
                $person->categoryValues()->create(['category_id' => $categoryId] + $row);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array<int, array<string, mixed>>
     */
    private static function rowsFor(Category $category, array $answer): array
    {
        if ($category->type === CategoryType::Boolean) {
            return is_bool($answer['value'] ?? null)
                ? [['value' => $answer['value']]]
                : [];
        }

        $optionIds = $category->type === CategoryType::Single
            ? [$answer['option_id'] ?? null]
            : (array) ($answer['option_ids'] ?? []);

        return array_values(array_filter(array_map(
            fn (mixed $id): array => is_string($id) && $id !== ''
                ? ['category_option_id' => $id]
                : [],
            $optionIds,
        )));
    }

    /**
     * @return array{value: bool|null, option_id: string|null, option_ids: array<int, string>}
     */
    private static function emptyAnswer(): array
    {
        return ['value' => null, 'option_id' => null, 'option_ids' => []];
    }
}
