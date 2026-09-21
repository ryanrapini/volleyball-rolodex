<?php

use App\Enums\TeamResponse;
use App\Models\Category;
use App\Models\Person;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Team Builder: a few questions, a deck of everyone eligible, and a saved team
 * whose members are marked as they answer.
 */

function teamFixture(): array
{
    $user = User::factory()->create();

    $level = Category::factory()->for($user)->single()->create([
        'name' => 'Skill level',
        'position' => 0,
        'in_team_builder' => true,
    ]);
    $a = $level->options()->create(['label' => 'A', 'position' => 0]);
    $bb = $level->options()->create(['label' => 'BB', 'position' => 1]);

    $height = Category::factory()->for($user)->boolean()->create([
        'name' => 'Under 6 ft',
        'position' => 1,
        'in_team_builder' => true,
    ]);

    $private = Category::factory()->for($user)->boolean()->create([
        'name' => 'Likes cake',
        'position' => 2,
        'in_team_builder' => false,
    ]);

    $ace = Person::factory()->for($user)->create(['name' => 'Ace Player']);
    $ace->categoryValues()->create(['category_id' => $level->id, 'category_option_id' => $a->id]);

    $bee = Person::factory()->for($user)->create(['name' => 'Bee Player']);
    $bee->categoryValues()->create(['category_id' => $level->id, 'category_option_id' => $bb->id]);

    $unranked = Person::factory()->for($user)->create(['name' => 'Unranked Player']);

    return [$user, $level, $a, $bb, $height, $private, $ace, $bee, $unranked];
}

test('only the categories marked for it become builder questions', function () {
    [$user] = teamFixture();

    $this->actingAs($user)
        ->get(route('teams.build'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Teams/Build')
            ->where('ready', true)
            ->has('questions', 2)
            ->where('questions.0.name', 'Skill level')
            ->where('questions.0.chips.2', ['value' => 'unset', 'label' => 'Unset'])
            ->where('questions.1.name', 'Under 6 ft')
        );
});

test('with nothing marked, the builder says so rather than asking nothing', function () {
    $user = User::factory()->create();
    Category::factory()->for($user)->boolean()->create(['name' => 'Can set', 'in_team_builder' => false]);

    $this->actingAs($user)
        ->get(route('teams.build'))
        ->assertInertia(fn (Assert $page) => $page->where('ready', false)->has('questions', 0));
});

test('a category can be marked for the team builder', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Skill level',
            'type' => 'boolean',
            'options' => [],
            'in_team_builder' => true,
        ])
        ->assertSessionHasNoErrors();

    $category = $user->categories()->sole();

    expect($category->in_team_builder)->toBeTrue();

    $this->actingAs($user)
        ->get(route('categories.edit', $category))
        ->assertInertia(fn (Assert $page) => $page->where('category.in_team_builder', true));
});

test('the deck holds only the people who match the answers', function () {
    [$user, $level, $a, $bb] = teamFixture();

    $this->actingAs($user)
        ->get(route('teams.deck', ['a' => [$level->id => $bb->id]]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Teams/Deck')
            ->has('eligible', 1)
            ->where('eligible.0.name', 'Bee Player')
        );

    // Unset finds the people nobody has ranked.
    $this->actingAs($user)
        ->get(route('teams.deck', ['a' => [$level->id => 'unset']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('eligible', 1)
            ->where('eligible.0.name', 'Unranked Player')
        );
});

test('a question left alone is not a filter', function () {
    [$user] = teamFixture();

    $this->actingAs($user)
        ->get(route('teams.deck'))
        ->assertInertia(fn (Assert $page) => $page->has('eligible', 3));
});

test('someone who said no to that date is not asked again', function () {
    [$user, , , , , , $ace] = teamFixture();

    $team = Team::factory()->for($user)->create([
        'name' => 'Last month',
        'tournament_date' => '2026-10-04',
    ]);
    $team->members()->create(['person_id' => $ace->id, 'response' => TeamResponse::No->value]);

    $this->actingAs($user)
        ->get(route('teams.deck', ['date' => '2026-10-04']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('eligible', 2)
            ->where('eligible', fn ($people): bool => collect($people)
                ->doesntContain(fn ($person): bool => ($person['name'] ?? null) === 'Ace Player'))
        );

    // A different date is a different ask.
    $this->actingAs($user)
        ->get(route('teams.deck', ['date' => '2026-11-01']))
        ->assertInertia(fn (Assert $page) => $page->has('eligible', 3));
});

test('saving a team keeps the people who were kept', function () {
    [$user, , , , , , $ace, $bee] = teamFixture();

    $this->actingAs($user)
        ->post(route('teams.store'), [
            'name' => 'Friday doubles',
            'tournament_date' => '2026-10-04',
            'person_ids' => [$ace->id, $bee->id],
        ])
        ->assertRedirect();

    $team = $user->teams()->sole();

    expect($team->name)->toBe('Friday doubles')
        ->and($team->tournament_date->toDateString())->toBe('2026-10-04')
        ->and($team->members()->count())->toBe(2)
        ->and($team->members()->pluck('response')->unique()->all())->toBe([TeamResponse::Waiting]);

    $this->actingAs($user)
        ->get(route('teams.show', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Teams/Show')
            ->has('members', 2)
            ->where('tally.waiting', 2)
            ->where('tally.total', 2)
        );
});

test('a team cannot be saved with somebody else in it', function () {
    [$user] = teamFixture();

    $stranger = User::factory()->create();
    $theirs = Person::factory()->for($stranger)->create(['name' => 'Not Yours']);

    $this->actingAs($user)
        ->post(route('teams.store'), [
            'name' => 'Sneaky',
            'person_ids' => [$theirs->id],
        ])
        ->assertSessionHasErrors('person_ids.0');

    expect($user->teams()->count())->toBe(0);
});

test('what they said is recorded, and the tally keeps up', function () {
    [$user, , , , , , $ace, $bee] = teamFixture();

    $team = Team::factory()->for($user)->create(['name' => 'Friday doubles']);
    $team->members()->create(['person_id' => $ace->id, 'position' => 0]);
    $team->members()->create(['person_id' => $bee->id, 'position' => 1]);

    $this->actingAs($user)
        ->patchJson(route('teams.respond', [$team, $ace]), ['response' => 'yes'])
        ->assertOk()
        ->assertJsonPath('response', 'yes')
        ->assertJsonPath('tally.yes', 1)
        ->assertJsonPath('tally.waiting', 1);

    $this->actingAs($user)
        ->patchJson(route('teams.respond', [$team, $bee]), ['response' => 'no'])
        ->assertOk()
        ->assertJsonPath('tally.no', 1);

    expect($team->members()->where('person_id', $ace->id)->sole()->response)->toBe(TeamResponse::Yes);
});

test('a response that is not one of the three is refused', function () {
    [$user, , , , , , $ace] = teamFixture();

    $team = Team::factory()->for($user)->create(['name' => 'Friday doubles']);
    $team->members()->create(['person_id' => $ace->id, 'position' => 0]);

    $this->actingAs($user)
        ->patchJson(route('teams.respond', [$team, $ace]), ['response' => 'maybe'])
        ->assertStatus(422);
});

test('somebody not on the team cannot be marked', function () {
    [$user, , , , , , $ace, $bee] = teamFixture();

    $team = Team::factory()->for($user)->create(['name' => 'Friday doubles']);
    $team->members()->create(['person_id' => $ace->id, 'position' => 0]);

    $this->actingAs($user)
        ->patchJson(route('teams.respond', [$team, $bee]), ['response' => 'yes'])
        ->assertNotFound();
});

test('another account cannot touch my team', function () {
    [$user, , , , , , $ace] = teamFixture();

    $team = Team::factory()->for($user)->create(['name' => 'Mine']);
    $team->members()->create(['person_id' => $ace->id, 'position' => 0]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('teams.show', $team))->assertForbidden();
    $this->actingAs($stranger)
        ->patchJson(route('teams.respond', [$team, $ace]), ['response' => 'yes'])
        ->assertForbidden();
    $this->actingAs($stranger)->delete(route('teams.destroy', $team))->assertForbidden();
});

test('deleting a team leaves the people alone', function () {
    [$user, , , , , , $ace] = teamFixture();

    $team = Team::factory()->for($user)->create(['name' => 'Friday doubles']);
    $team->members()->create(['person_id' => $ace->id, 'position' => 0]);

    $this->actingAs($user)
        ->delete(route('teams.destroy', $team))
        ->assertRedirect(route('teams.index'));

    expect($user->teams()->count())->toBe(0)
        ->and($user->people()->count())->toBe(3);
});

test('the teams list shows what each one is up to', function () {
    [$user, , , , , , $ace, $bee] = teamFixture();

    $team = Team::factory()->for($user)->create([
        'name' => 'Friday doubles',
        'tournament_date' => '2026-10-04',
    ]);
    $team->members()->create(['person_id' => $ace->id, 'position' => 0, 'response' => TeamResponse::Yes->value]);
    $team->members()->create(['person_id' => $bee->id, 'position' => 1]);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Teams/Index')
            ->has('teams', 1)
            ->where('teams.0.name', 'Friday doubles')
            ->where('teams.0.members_count', 2)
            ->where('teams.0.tally.yes', 1)
            ->where('teams.0.tally.waiting', 1)
        );
});
