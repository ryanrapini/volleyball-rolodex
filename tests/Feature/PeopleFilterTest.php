<?php

use App\Models\Category;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Three people with overlapping answers across three categories.
 *
 * @return array{0: User, 1: array<string, mixed>}
 */
function filterFixture(): array
{
    $user = User::factory()->create();

    $canSet = Category::factory()->for($user)->boolean()->create(['name' => 'Can set', 'position' => 0]);
    $level = Category::factory()->for($user)->single()->create(['name' => 'Skill level', 'position' => 1]);
    $a = $level->options()->create(['label' => 'A', 'position' => 0]);
    $bb = $level->options()->create(['label' => 'BB', 'position' => 1]);
    $position = Category::factory()->for($user)->multiple()->create(['name' => 'Position', 'position' => 2]);
    $setter = $position->options()->create(['label' => 'Setter', 'position' => 0]);
    $middle = $position->options()->create(['label' => 'Middle blocker', 'position' => 1]);

    $setterGuy = Person::factory()->for($user)->create(['name' => 'Setter Guy']);
    $setterGuy->categoryValues()->create(['category_id' => $canSet->id, 'value' => true]);
    $setterGuy->categoryValues()->create(['category_id' => $level->id, 'category_option_id' => $a->id]);
    $setterGuy->categoryValues()->create(['category_id' => $position->id, 'category_option_id' => $setter->id]);

    $middleGuy = Person::factory()->for($user)->create(['name' => 'Middle Guy']);
    $middleGuy->categoryValues()->create(['category_id' => $canSet->id, 'value' => false]);
    $middleGuy->categoryValues()->create(['category_id' => $level->id, 'category_option_id' => $bb->id]);
    $middleGuy->categoryValues()->create(['category_id' => $position->id, 'category_option_id' => $middle->id]);

    Person::factory()->for($user)->create(['name' => 'No Answers']);

    return [$user, compact('canSet', 'level', 'a', 'bb', 'position', 'setter', 'middle')];
}

test('a yes chip keeps only the people marked yes', function () {
    [$user, $c] = filterFixture();

    $this->actingAs($user)
        ->get(route('people.index', ['f' => [$c['canSet']->id => 'yes']]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.total', 1)
            ->where('people.data.0.name', 'Setter Guy')
            ->etc());
});

test('a no chip keeps only the people explicitly marked no', function () {
    [$user, $c] = filterFixture();

    $this->actingAs($user)
        ->get(route('people.index', ['f' => [$c['canSet']->id => 'no']]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.total', 1)
            ->where('people.data.0.name', 'Middle Guy')
            ->etc());
});

test('selecting several choices of one category matches any of them', function () {
    [$user, $c] = filterFixture();

    $this->actingAs($user)
        ->get(route('people.index', [
            'f' => [$c['position']->id => $c['setter']->id.','.$c['middle']->id],
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.total', 2)
            ->etc());
});

test('chips across categories are combined', function () {
    [$user, $c] = filterFixture();

    $this->actingAs($user)
        ->get(route('people.index', [
            'f' => [
                $c['canSet']->id => 'yes',
                $c['position']->id => $c['setter']->id,
            ],
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.total', 1)
            ->where('people.data.0.name', 'Setter Guy')
            ->etc());

    // The same pair with a position nobody with "can set" plays.
    $this->actingAs($user)
        ->get(route('people.index', [
            'f' => [
                $c['canSet']->id => 'yes',
                $c['position']->id => $c['middle']->id,
            ],
        ]))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 0)->etc());
});

test('chips and the search box narrow the list together', function () {
    [$user, $c] = filterFixture();

    $this->actingAs($user)
        ->get(route('people.index', [
            'q' => 'guy',
            'f' => [$c['canSet']->id => 'yes'],
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.total', 1)
            ->where('people.data.0.name', 'Setter Guy')
            ->etc());

    $this->actingAs($user)
        ->get(route('people.index', [
            'q' => 'nobody',
            'f' => [$c['canSet']->id => 'yes'],
        ]))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 0)->etc());
});

test('unknown chips and values are ignored rather than trusted', function () {
    [$user, $c] = filterFixture();

    $this->actingAs($user)
        ->get(route('people.index', [
            'f' => [
                'not-a-real-category' => 'yes',
                $c['canSet']->id => 'nonsense',
                $c['level']->id => 'not-an-option',
            ],
        ]))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 3)->etc());
});

test('another user cannot filter into your rolodex', function () {
    [, $c] = filterFixture();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('people.index', ['f' => [$c['canSet']->id => 'yes']]))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 0)->etc());
});

test('the chips offered describe every category and its current selection', function () {
    [$user, $c] = filterFixture();

    $this->actingAs($user)
        ->get(route('people.index', ['f' => [$c['canSet']->id => 'yes']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterOptions', 3)
            ->where('filterOptions.0.name', 'Can set')
            ->where('filterOptions.0.type', 'boolean')
            ->where('filterOptions.0.chips.0.label', 'Yes')
            ->where('filterOptions.0.chips.1.label', 'No')
            ->where('filterOptions.0.selected', ['yes'])
            ->where('filterOptions.1.name', 'Skill level')
            ->where('filterOptions.1.chips.0.label', 'A')
            ->where('filterOptions.1.selected', [])
            ->where('filterOptions.2.selected', [])
            ->etc());
});
