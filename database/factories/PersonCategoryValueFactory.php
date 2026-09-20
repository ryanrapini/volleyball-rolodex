<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Person;
use App\Models\PersonCategoryValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonCategoryValue>
 */
class PersonCategoryValueFactory extends Factory
{
    protected $model = PersonCategoryValue::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'category_id' => Category::factory(),
            'category_option_id' => null,
            'value' => true,
        ];
    }
}
