<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => CategoryType::Boolean,
            'position' => 0,
        ];
    }

    public function boolean(): static
    {
        return $this->state(fn () => ['type' => CategoryType::Boolean]);
    }

    public function single(): static
    {
        return $this->state(fn () => ['type' => CategoryType::Single]);
    }

    public function multiple(): static
    {
        return $this->state(fn () => ['type' => CategoryType::Multiple]);
    }
}
