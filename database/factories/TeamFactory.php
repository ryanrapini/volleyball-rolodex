<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'tournament_date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
        ];
    }

    public function withoutDate(): static
    {
        return $this->state(fn (): array => ['tournament_date' => null]);
    }

    /**
     * Add people to the team, in order.
     *
     * @param  array<int, Person>  $people
     */
    public function with(array $people): static
    {
        return $this->afterCreating(function (Team $team) use ($people): void {
            foreach ($people as $position => $person) {
                $team->members()->create([
                    'person_id' => $person->getKey(),
                    'position' => $position,
                ]);
            }
        });
    }
}
