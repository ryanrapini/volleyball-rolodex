<?php

use App\Models\Category;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Each category decides how it shows up on a person's card: whether it appears
 * at all, whether its name comes along with the answer, and what colour the
 * answer is painted.
 */

function cardDisplayFixture(): array
{
    $user = User::factory()->create();

    $canSet = Category::factory()->for($user)->boolean()->create(['name' => 'Can set', 'position' => 0]);
    $level = Category::factory()->for($user)->single()->create(['name' => 'Skill level', 'position' => 1]);
    $bb = $level->options()->create(['label' => 'BB', 'position' => 0]);

    return [$user, $canSet, $level, $bb];
}

test('a category can be kept off the card', function () {
    [$user, $canSet] = cardDisplayFixture();

    $person = Person::factory()->for($user)->create(['name' => 'Marcus Hale']);
    $person->categoryValues()->create(['category_id' => $canSet->id, 'value' => true]);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page->where('people.data.0.tags.0.label', 'Can set'));

    $canSet->update(['show_on_card' => false]);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page->where('people.data.0.tags', []));
});

test('a choice category can show just its answer', function () {
    [$user, , $level, $bb] = cardDisplayFixture();

    $person = Person::factory()->for($user)->create(['name' => 'Marcus Hale']);
    $person->categoryValues()->create(['category_id' => $level->id, 'category_option_id' => $bb->id]);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page->where('people.data.0.tags.0.label', 'Skill level: BB'));

    $level->update(['show_name_on_card' => false]);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page->where('people.data.0.tags.0.label', 'BB'));
});

test('answers carry their own colour onto the card', function () {
    [$user, $canSet, $level, $bb] = cardDisplayFixture();

    $canSet->update(['colour' => '#2563eb']);
    $bb->update(['colour' => '#16a34a']);

    $person = Person::factory()->for($user)->create(['name' => 'Marcus Hale']);
    $person->categoryValues()->create(['category_id' => $canSet->id, 'value' => true]);
    $person->categoryValues()->create(['category_id' => $level->id, 'category_option_id' => $bb->id]);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.data.0.tags.0', ['label' => 'Can set', 'colour' => '#2563eb'])
            ->where('people.data.0.tags.1', ['label' => 'Skill level: BB', 'colour' => '#16a34a'])
        );
});

test('a category with no colour of its own stays plain', function () {
    [$user, $canSet] = cardDisplayFixture();

    $person = Person::factory()->for($user)->create(['name' => 'Marcus Hale']);
    $person->categoryValues()->create(['category_id' => $canSet->id, 'value' => true]);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.data.0.tags.0', ['label' => 'Can set', 'colour' => null])
        );
});

test('colours are saved against the choices the form was showing', function () {
    [$user, , $level, $bb] = cardDisplayFixture();
    $a = $level->options()->create(['label' => 'A', 'position' => 2]);

    $this->actingAs($user)
        ->patch(route('categories.update', $level), [
            'name' => 'Skill level',
            'type' => 'single',
            'options' => ['A', 'BB'],
            'option_colours' => ['#db2777', '#2563eb'],
            'show_on_card' => true,
            'show_name_on_card' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($a->refresh()->colour)->toBe('#db2777')
        ->and($bb->refresh()->colour)->toBe('#2563eb')
        ->and($level->refresh()->show_name_on_card)->toBeFalse();

    // And the form gets them back, still lined up with the choices.
    $this->actingAs($user)
        ->get(route('categories.edit', $level))
        ->assertInertia(fn (Assert $page) => $page
            ->where('category.option_colours', ['#db2777', '#2563eb'])
            ->where('category.show_name_on_card', false)
        );
});

test('a colour that is not a colour is dropped rather than refused', function () {
    [$user, $canSet] = cardDisplayFixture();

    $this->actingAs($user)
        ->patch(route('categories.update', $canSet), [
            'name' => 'Can set',
            'type' => 'boolean',
            'options' => [],
            'colour' => 'chartreuse',
            'show_on_card' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($canSet->refresh()->colour)->toBeNull()
        ->and($canSet->show_on_card)->toBeFalse();
});

test('a new category arrives with the card settings already on', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Plays on',
            'type' => 'single',
            'options' => ['Sand', 'Indoor'],
        ])
        ->assertSessionHasNoErrors();

    $category = $user->categories()->sole();

    expect($category->show_on_card)->toBeTrue()
        ->and($category->show_name_on_card)->toBeTrue()
        ->and($category->colour)->toBeNull()
        ->and($category->options()->count())->toBe(2);
});
