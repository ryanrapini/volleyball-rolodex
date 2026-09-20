<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryOption>
 */
class CategoryOptionFactory extends Factory
{
    protected $model = CategoryOption::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'label' => fake()->unique()->word(),
            'position' => 0,
        ];
    }
}
