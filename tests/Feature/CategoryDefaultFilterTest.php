<?php

use App\Models\Category;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A category carries the filter state the people list should open in, so the
 * list starts the way the owner wants without touching the chips each time.
 */

test('a yes / no category can default the list to yes', function () {
    $user = User::factory()->create();
    $active = Category::factory()->for($user)->boolean()->create(['name' => 'Active', 'position' => 0]);

    $playing = Person::factory()->for($user)->create(['name' => 'Playing']);
    $playing->categoryValues()->create(['category_id' => $active->id, 'value' => true]);
    Person::factory()->for($user)->create(['name' => 'Not playing']);

    $this->actingAs($user)
        ->patch(route('categories.update', $active), [
            'name' => 'Active',
            'type' => 'boolean',
            'options' => [],
            'default_filter' => ['yes'],
        ])
        ->assertSessionHasNoErrors();

    expect($active->refresh()->default_filter)->toBe(['yes']);

    // With nothing asked for, the list opens already filtered.
    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Index')
            ->where('filters.categories.'.$active->id.'.0', 'yes')
            ->has('people.data', 1)
            ->where('people.data.0.name', 'Playing')
        );
});

test('clearing that filter overrides the default', function () {
    $user = User::factory()->create();
    $active = Category::factory()->for($user)->boolean()->create(['name' => 'Active', 'position' => 0]);
    $active->update(['default_filter' => ['yes']]);

    $playing = Person::factory()->for($user)->create(['name' => 'Playing']);
    $playing->categoryValues()->create(['category_id' => $active->id, 'value' => true]);
    Person::factory()->for($user)->create(['name' => 'Not playing']);

    // An empty value is "cleared", and has to beat the default.
    $this->actingAs($user)
        ->get(route('people.index', ['f' => [$active->id => '']]))
        ->assertInertia(fn (Assert $page) => $page->has('people.data', 2));
});

test('choosing the other value overrides the default too', function () {
    $user = User::factory()->create();
    $active = Category::factory()->for($user)->boolean()->create(['name' => 'Active', 'position' => 0]);
    $active->update(['default_filter' => ['yes']]);

    $playing = Person::factory()->for($user)->create(['name' => 'Playing']);
    $playing->categoryValues()->create(['category_id' => $active->id, 'value' => true]);

    $resting = Person::factory()->for($user)->create(['name' => 'Resting']);
    $resting->categoryValues()->create(['category_id' => $active->id, 'value' => false]);

    $this->actingAs($user)
        ->get(route('people.index', ['f' => [$active->id => 'no']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 1)
            ->where('people.data.0.name', 'Resting')
        );
});

test('a choice category can default to one of its choices', function () {
    $user = User::factory()->create();
    $level = Category::factory()->for($user)->single()->create(['name' => 'Skill level', 'position' => 0]);
    $a = $level->options()->create(['label' => 'A', 'position' => 0]);
    $level->options()->create(['label' => 'BB', 'position' => 1]);

    $ranked = Person::factory()->for($user)->create(['name' => 'Player A']);
    $ranked->categoryValues()->create(['category_id' => $level->id, 'category_option_id' => $a->id]);
    Person::factory()->for($user)->create(['name' => 'Unranked']);

    // The form sends positions, because a category being created has no ids yet.
    $this->actingAs($user)
        ->patch(route('categories.update', $level), [
            'name' => 'Skill level',
            'type' => 'single',
            'options' => ['A', 'BB'],
            'default_filter' => [0],
        ])
        ->assertSessionHasNoErrors();

    expect($level->refresh()->default_filter)->toBe([$a->id]);

    // And the form gets positions back.
    $this->actingAs($user)
        ->get(route('categories.edit', $level))
        ->assertInertia(fn (Assert $page) => $page->where('category.default_filter', [0]));

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 1)
            ->where('people.data.0.name', 'Player A')
        );
});

test('a default that is not a choice in the category is refused', function () {
    $user = User::factory()->create();
    $level = Category::factory()->for($user)->single()->create(['name' => 'Skill level', 'position' => 0]);
    $level->options()->create(['label' => 'A', 'position' => 0]);

    $this->actingAs($user)
        ->patch(route('categories.update', $level), [
            'name' => 'Skill level',
            'type' => 'single',
            'options' => ['A'],
            'default_filter' => [7],
        ])
        ->assertSessionHasErrors('default_filter');

    expect($level->refresh()->default_filter)->toBeNull();
});

test('a pick-one category cannot default to two choices', function () {
    $user = User::factory()->create();
    $level = Category::factory()->for($user)->single()->create(['name' => 'Skill level', 'position' => 0]);
    $level->options()->create(['label' => 'A', 'position' => 0]);
    $level->options()->create(['label' => 'BB', 'position' => 1]);

    $this->actingAs($user)
        ->patch(route('categories.update', $level), [
            'name' => 'Skill level',
            'type' => 'single',
            'options' => ['A', 'BB'],
            'default_filter' => [0, 1],
        ])
        ->assertSessionHasErrors('default_filter');
});

test('a pick-any category can default to several', function () {
    $user = User::factory()->create();
    $surface = Category::factory()->for($user)->multiple()->create(['name' => 'Plays on', 'position' => 0]);
    $sand = $surface->options()->create(['label' => 'Sand', 'position' => 0]);
    $indoor = $surface->options()->create(['label' => 'Indoor', 'position' => 1]);

    $this->actingAs($user)
        ->patch(route('categories.update', $surface), [
            'name' => 'Plays on',
            'type' => 'multiple',
            'options' => ['Sand', 'Indoor'],
            'default_filter' => [1, 0],
        ])
        ->assertSessionHasNoErrors();

    expect($surface->refresh()->default_filter)->toBe([$indoor->id, $sand->id]);
});
