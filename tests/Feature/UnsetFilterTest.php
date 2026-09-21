<?php

use App\Models\Category;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * "Unset" is a filter on the absence of an answer, which is the one thing that
 * cannot be written down as a value on a person. It is offered on every category;
 * nobody has a favourite colour until they pick one.
 */

function unsetFixture(): array
{
    $user = User::factory()->create();

    $canSet = Category::factory()->for($user)->boolean()->create(['name' => 'Can set', 'position' => 0]);
    $level = Category::factory()->for($user)->single()->create(['name' => 'Skill level', 'position' => 1]);
    $a = $level->options()->create(['label' => 'A', 'position' => 0]);
    $bb = $level->options()->create(['label' => 'BB', 'position' => 1]);

    $answered = Person::factory()->for($user)->create(['name' => 'Answered']);
    $answered->categoryValues()->create(['category_id' => $level->id, 'category_option_id' => $a->id]);

    $silent = Person::factory()->for($user)->create(['name' => 'Silent']);

    return [$user, $canSet, $level, $a, $bb, $answered, $silent];
}

test('every category offers unset alongside its answers', function () {
    [$user] = unsetFixture();

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filterOptions.0.chips.2', ['value' => 'unset', 'label' => 'Unset'])
            ->where('filterOptions.1.chips.2', ['value' => 'unset', 'label' => 'Unset'])
            ->etc());
});

test('filtering on unset finds the people with no answer', function () {
    [$user, $canSet, $level] = unsetFixture();

    $this->actingAs($user)
        ->get(route('people.index', ['f' => [$level->id => 'unset']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 1)
            ->where('people.data.0.name', 'Silent')
        );
});

test('unset can be combined with an answer', function () {
    [$user, $canSet, $level, $a] = unsetFixture();

    $this->actingAs($user)
        ->get(route('people.index', ['f' => [$level->id => 'unset,'.$a->id]]))
        ->assertInertia(fn (Assert $page) => $page->has('people.data', 2));
});

test('an explicit no is not unset', function () {
    [$user, $canSet] = unsetFixture();

    $person = Person::factory()->for($user)->create(['name' => 'Answered no']);
    $person->categoryValues()->create(['category_id' => $canSet->id, 'value' => false]);

    $this->actingAs($user)
        ->get(route('people.index', ['f' => [$canSet->id => 'unset']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 2)
            ->where('people.data.0.name', 'Answered')
            ->where('people.data.1.name', 'Silent')
        );
});

test('a category can open filtered to unset', function () {
    [$user, $canSet, $level] = unsetFixture();

    $level->update(['default_filter' => ['unset']]);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.categories.'.$level->id.'.0', 'unset')
            ->where('filterOptions.1.default_filter', ['unset'])
            ->has('people.data', 1)
            ->where('people.data.0.name', 'Silent')
        );
});

test('the filter control can save the default it is showing', function () {
    [$user, $canSet, $level, $a] = unsetFixture();

    $this->actingAs($user)
        ->from(route('people.index'))
        ->post(route('categories.default-filter', $level), ['values' => [$a->id, 'unset']])
        ->assertRedirect(route('people.index'))
        ->assertSessionHasNoErrors();

    expect($level->refresh()->default_filter)->toBe([$a->id, 'unset']);

    // And clearing it puts the list back to everybody.
    $this->actingAs($user)
        ->from(route('people.index'))
        ->post(route('categories.default-filter', $level), ['values' => []]);

    expect($level->refresh()->default_filter)->toBe([]);
});

test('the filter control refuses an answer the category does not have', function () {
    [$user, $canSet, $level] = unsetFixture();

    $other = Category::factory()->for($user)->single()->create(['name' => 'Plays on', 'position' => 5]);
    $sand = $other->options()->create(['label' => 'Sand', 'position' => 0]);

    $this->actingAs($user)
        ->from(route('people.index'))
        ->post(route('categories.default-filter', $level), ['values' => [$sand->id]])
        ->assertSessionHas('error');

    expect($level->refresh()->default_filter)->toBeNull();
});

test('a pick-one category will not default to two answers', function () {
    [$user, $canSet, $level, $a, $bb] = unsetFixture();

    $this->actingAs($user)
        ->from(route('people.index'))
        ->post(route('categories.default-filter', $level), ['values' => [$a->id, $bb->id]])
        ->assertSessionHas('error');

    expect($level->refresh()->default_filter)->toBeNull();
});

test('someone else cannot set the default on my category', function () {
    [$user, $canSet, $level] = unsetFixture();

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('categories.default-filter', $level), ['values' => ['unset']])
        ->assertForbidden();

    expect($level->refresh()->default_filter)->toBeNull();
});
