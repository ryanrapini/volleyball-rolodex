<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName().' '.fake()->lastName(),
            'phone' => fake()->numerify('(###) ###-####'),
            'email' => fake()->safeEmail(),
            'notes' => fake()->sentence(),
        ];
    }

    public function withoutContactDetails(): static
    {
        return $this->state(fn () => [
            'phone' => null,
            'email' => null,
            'notes' => null,
        ]);
    }
}
