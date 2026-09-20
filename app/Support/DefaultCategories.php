<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;

/**
 * The categories a new account starts with. They are a starting point, not a
 * schema: everything here can be renamed, retyped or deleted from the settings
 * screen.
 */
class DefaultCategories
{
    /**
     * @return array<int, array{name: string, type: CategoryType, options?: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'Plays as',
                'type' => CategoryType::Single,
                'options' => ["Men's net", "Women's net", 'Either'],
            ],
            ['name' => 'Under 6 ft', 'type' => CategoryType::Boolean],
            ['name' => 'Can set', 'type' => CategoryType::Boolean],
            ['name' => 'Can hit', 'type' => CategoryType::Boolean],
            ['name' => 'Can pass', 'type' => CategoryType::Boolean],
            ['name' => 'Can block', 'type' => CategoryType::Boolean],
            [
                'name' => 'Position',
                'type' => CategoryType::Multiple,
                'options' => ['Setter', 'Outside hitter', 'Middle blocker', 'Opposite', 'Libero / DS'],
            ],
            [
                'name' => 'Skill level',
                'type' => CategoryType::Single,
                'options' => ['A', 'BB', 'B', 'C', 'Beginner'],
            ],
            [
                'name' => 'Plays on',
                'type' => CategoryType::Multiple,
                'options' => ['Indoor', 'Grass', 'Sand'],
            ],
            [
                'name' => 'Availability',
                'type' => CategoryType::Multiple,
                'options' => ['Weekday evenings', 'Weekends', 'Daytime'],
            ],
        ];
    }

    /**
     * Give a brand new account its starter categories.
     */
    public static function seedFor(User $user): void
    {
        foreach (self::all() as $position => $definition) {
            /** @var Category $category */
            $category = $user->categories()->create([
                'name' => $definition['name'],
                'type' => $definition['type'],
                'position' => $position,
            ]);

            foreach ($definition['options'] ?? [] as $order => $label) {
                $category->options()->create([
                    'label' => $label,
                    'position' => $order,
                ]);
            }
        }
    }
}
