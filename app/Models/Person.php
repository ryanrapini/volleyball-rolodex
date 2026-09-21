<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Support\PersonPhotos;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['name', 'phone', 'email', 'notes'])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * This person's answers to their owner's categories.
     *
     * @return HasMany<PersonCategoryValue, $this>
     */
    public function categoryValues(): HasMany
    {
        return $this->hasMany(PersonCategoryValue::class);
    }

    /**
     * The tags shown on a person's card. A category can be kept off the card
     * entirely, can hide its own name, and each answer carries its own colour.
     *
     * @return array<int, array{label: string, colour: ?string}>
     */
    public function categoryTags(int $limit = 6): array
    {
        $tags = [];

        foreach ($this->orderedValues()->groupBy('category_id') as $group) {
            $category = $group->first()->category;

            if ($category === null || ! $category->show_on_card) {
                continue;
            }

            if ($category->type === CategoryType::Boolean) {
                if ($group->contains(fn (PersonCategoryValue $value): bool => $value->value === true)) {
                    // "Under 6 ft" has no value of its own: the name is the
                    // whole message.
                    $tags[] = ['label' => $category->name, 'colour' => $category->colour];
                }
            } else {
                $answers = $group
                    ->map(fn (PersonCategoryValue $value): ?array => $value->option === null
                        ? null
                        : [
                            'label' => $value->option->label,
                            'colour' => $value->option->colour,
                        ])
                    ->filter()
                    ->unique('label')
                    ->values();

                foreach ($answers as $answer) {
                    $tags[] = [
                        'label' => $category->show_name_on_card
                            ? $category->name.': '.$answer['label']
                            : $answer['label'],
                        'colour' => $answer['colour'],
                    ];
                }
            }

            if (count($tags) >= $limit) {
                return array_slice($tags, 0, $limit);
            }
        }

        return $tags;
    }

    /**
     * Every recorded answer, grouped by category, for the detail page. Unlike
     * the card, an explicit "no" is worth showing here.
     *
     * @return array<int, array{name: string, type: string, answer: string}>
     */
    public function answerGroups(): array
    {
        $groups = [];

        foreach ($this->orderedValues() as $value) {
            $category = $value->category;

            if ($category === null) {
                continue;
            }

            $label = $category->type === CategoryType::Boolean
                ? ($value->value === true ? 'Yes' : 'No')
                : $value->label();

            if ($label === null) {
                continue;
            }

            $key = $category->getKey();
            $groups[$key] ??= ['name' => $category->name, 'type' => $category->type->value, 'answers' => []];
            $groups[$key]['answers'][] = $label;
        }

        return array_values(array_map(
            fn (array $group): array => [
                'name' => $group['name'],
                'type' => $group['type'],
                'answer' => implode(', ', $group['answers']),
            ],
            $groups,
        ));
    }

    /**
     * Keep the searchable digits in step with whatever format the user typed,
     * and take the photo file with the record when it goes.
     */
    protected static function booted(): void
    {
        static::saving(function (Person $person): void {
            $person->phone_digits = self::phoneDigits($person->phone);
        });

        static::deleted(function (Person $person): void {
            PersonPhotos::forget($person->photo_path);
        });
    }

    /**
     * Reduce a phone number to searchable digits, or null when there are none.
     */
    public static function phoneDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * Answers in the order the owner arranged their categories.
     *
     * @return Collection<int, PersonCategoryValue>
     */
    private function orderedValues(): Collection
    {
        return $this->categoryValues
            ->sortBy(fn (PersonCategoryValue $value): string => sprintf(
                '%05d',
                $value->category?->position ?? 99999,
            ))
            ->values();
    }
}
