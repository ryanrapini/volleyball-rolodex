<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Category answers read as filters, in one place.
 *
 * The people list and the team builder have to mean the same thing by "skill level
 * BB, or nobody has said yet", so neither one owns the rules.
 */
class CategoryFilters
{
    public const YES = 'yes';

    public const NO = 'no';

    /** Nobody has answered this category at all. */
    public const UNSET = 'unset';

    /**
     * The chips a category offers: its answers, plus "unset".
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function chips(Category $category): array
    {
        $answers = $category->type === CategoryType::Boolean
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
                ->all();

        return array_merge($answers, [['value' => self::UNSET, 'label' => 'Unset']]);
    }

    /**
     * What the request is asking for.
     *
     * A category that was not mentioned opens in its default state — unless the
     * caller says otherwise, which the team builder does: there, an unanswered
     * question simply means no filter. A category mentioned but empty has been
     * cleared, and that always beats a default.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<string, array<int, string>>
     */
    public static function read(mixed $submitted, Collection $categories, bool $useDefaults = true): array
    {
        $submitted = is_array($submitted) ? $submitted : [];

        $filters = [];

        foreach ($categories as $category) {
            $allowed = array_merge(self::allowed($category), [self::UNSET]);

            if (! array_key_exists($category->getKey(), $submitted)) {
                if (! $useDefaults) {
                    continue;
                }

                $defaults = array_values(array_intersect($category->default_filter ?? [], $allowed));

                if ($defaults !== []) {
                    $filters[$category->getKey()] = $defaults;
                }

                continue;
            }

            $given = $submitted[$category->getKey()];
            $given = is_array($given) ? implode(',', $given) : (string) $given;

            $kept = array_values(array_unique(array_filter(
                array_map('trim', explode(',', $given)),
                fn (string $value): bool => in_array($value, $allowed, true),
            )));

            if ($kept !== []) {
                $filters[$category->getKey()] = $kept;
            }
        }

        return $filters;
    }

    /**
     * Narrow a query of people to those matching every filter. Values inside one
     * category are alternatives; separate categories all have to hold.
     *
     * @param  array<string, array<int, string>>  $filters
     */
    public static function apply(Builder $query, array $filters): void
    {
        foreach ($filters as $categoryId => $values) {
            // "Unset" is not an answer, it is the absence of one, so it reads as
            // "has no value row for this category" rather than a value to match.
            $unset = in_array(self::UNSET, $values, true);
            $yesNo = array_intersect($values, [self::YES, self::NO]);
            $optionIds = array_diff($values, [self::YES, self::NO, self::UNSET]);

            $query->where(function (Builder $match) use ($categoryId, $yesNo, $optionIds, $unset): void {
                if ($unset) {
                    $match->orWhereDoesntHave('categoryValues', fn (Builder $value) => $value
                        ->where('category_id', $categoryId));
                }

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
     * The answers a category can hold: yes / no, or its choice ids.
     *
     * @return array<int, string>
     */
    public static function allowed(Category $category): array
    {
        return $category->type === CategoryType::Boolean
            ? [self::YES, self::NO]
            : $category->options->pluck('id')->all();
    }
}
