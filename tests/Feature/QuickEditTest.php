<?php

use App\Models\Category;
use App\Models\Person;
use App\Models\User;

/**
 * @return array{0: User, 1: array<string, mixed>}
 */
function quickEditFixture(): array
{
    $user = User::factory()->create();

    $canSet = Category::factory()->for($user)->boolean()->create(['name' => 'Can set', 'position' => 0]);
    $level = Category::factory()->for($user)->single()->create(['name' => 'Skill level', 'position' => 1]);
    $a = $level->options()->create(['label' => 'A', 'position' => 0]);
    $bb = $level->options()->create(['label' => 'BB', 'position' => 1]);
    $position = Category::factory()->for($user)->multiple()->create(['name' => 'Position', 'position' => 2]);
    $setter = $position->options()->create(['label' => 'Setter', 'position' => 0]);
    $middle = $position->options()->create(['label' => 'Middle blocker', 'position' => 1]);

    return [$user, compact('canSet', 'level', 'a', 'bb', 'position', 'setter', 'middle')];
}

test('the popup gets the whole record, not the card excerpt', function () {
    [$user, $c] = quickEditFixture();

    $person = Person::factory()->for($user)->create([
        'name' => 'Marcus Hale',
        'phone' => '555-0100',
        'email' => 'marcus@example.com',
        'notes' => str_repeat('Long note. ', 40),
    ]);
    $person->categoryValues()->create(['category_id' => $c['canSet']->id, 'value' => true]);
    $person->categoryValues()->create(['category_id' => $c['level']->id, 'category_option_id' => $c['bb']->id]);
    $person->categoryValues()->create(['category_id' => $c['position']->id, 'category_option_id' => $c['setter']->id]);

    $response = $this->actingAs($user)
        ->getJson(route('people.quick-edit', $person))
        ->assertOk();

    // The index only carries notes_excerpt; the popup must not truncate.
    $response->assertJsonPath('person.notes', str_repeat('Long note. ', 40))
        ->assertJsonPath('person.phone', '555-0100')
        ->assertJsonPath('categories.0.name', 'Can set')
        ->assertJsonPath("answers.{$c['canSet']->id}.value", true)
        ->assertJsonPath("answers.{$c['level']->id}.option_id", $c['bb']->id)
        ->assertJsonPath("answers.{$c['position']->id}.option_ids", [$c['setter']->id]);
});

test('the popup can change any field and any answer', function () {
    [$user, $c] = quickEditFixture();
    $person = Person::factory()->for($user)->create(['name' => 'Old Name']);

    $this->actingAs($user)
        ->postJson(route('people.quick-update', $person), [
            'name' => 'New Name',
            'phone' => '555-0111',
            'email' => 'new@example.com',
            'notes' => 'Changed from the list.',
            'answers' => [
                ['category_id' => $c['canSet']->id, 'value' => true],
                ['category_id' => $c['level']->id, 'option_id' => $c['a']->id],
                ['category_id' => $c['position']->id, 'option_ids' => [$c['setter']->id, $c['middle']->id]],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('person.name', 'New Name')
        ->assertJsonPath('person.tags', ['Can set', 'Skill level: A', 'Position: Setter, Middle blocker']);

    $person->refresh();

    expect($person->name)->toBe('New Name')
        ->and($person->phone)->toBe('555-0111')
        ->and($person->notes)->toBe('Changed from the list.')
        ->and($person->categoryValues()->count())->toBe(4);
});

test('the response carries what the card needs to redraw', function () {
    [$user] = quickEditFixture();
    $person = Person::factory()->for($user)->create([
        'name' => 'Dana Okafor',
        'notes' => str_repeat('x', 400),
    ]);

    $this->actingAs($user)
        ->postJson(route('people.quick-update', $person), ['name' => 'Dana Okafor'])
        ->assertOk()
        ->assertJsonPath('person.id', $person->id)
        ->assertJsonPath('person.notes_excerpt', str_repeat('x', 120).'...');
});

test('an answer can be cleared from the popup', function () {
    [$user, $c] = quickEditFixture();
    $person = Person::factory()->for($user)->create();
    $person->categoryValues()->create(['category_id' => $c['canSet']->id, 'value' => true]);

    $this->actingAs($user)
        ->postJson(route('people.quick-update', $person), [
            'name' => $person->name,
            // No "value" key at all means "not recorded", not "no".
            'answers' => [['category_id' => $c['canSet']->id]],
        ])
        ->assertOk();

    expect($person->categoryValues()->count())->toBe(0);
});

test('an explicit no is different from cleared', function () {
    [$user, $c] = quickEditFixture();
    $person = Person::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('people.quick-update', $person), [
            'name' => $person->name,
            'answers' => [['category_id' => $c['canSet']->id, 'value' => false]],
        ])
        ->assertOk();

    expect($person->categoryValues()->sole()->value)->toBeFalse();
});

test('the popup reports validation problems instead of saving', function () {
    [$user] = quickEditFixture();
    $person = Person::factory()->for($user)->create(['name' => 'Marcus Hale']);

    $this->actingAs($user)
        ->postJson(route('people.quick-update', $person), [
            'name' => '',
            'email' => 'not-an-email',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email']);

    expect($person->refresh()->name)->toBe('Marcus Hale');
});

test('saving only categories leaves the core details alone', function () {
    [$user, $c] = quickEditFixture();

    $person = Person::factory()->for($user)->create([
        'name' => 'Marcus Hale',
        'phone' => '555-0100',
        'email' => 'marcus@example.com',
        'notes' => 'Keep these notes.',
    ]);

    // The tags popup sends the name, which the endpoint requires, and the
    // answers — and deliberately nothing else.
    $this->actingAs($user)
        ->postJson(route('people.quick-update', $person), [
            'name' => 'Marcus Hale',
            'answers' => [['category_id' => $c['canSet']->id, 'value' => true]],
        ])
        ->assertOk();

    $person->refresh();

    expect($person->phone)->toBe('555-0100')
        ->and($person->email)->toBe('marcus@example.com')
        ->and($person->notes)->toBe('Keep these notes.')
        ->and($person->categoryValues()->sole()->value)->toBeTrue();
});

test('saving only details leaves the categories alone', function () {
    [$user, $c] = quickEditFixture();

    $person = Person::factory()->for($user)->create(['name' => 'Dana Okafor']);
    $person->categoryValues()->create(['category_id' => $c['canSet']->id, 'value' => true]);
    $person->categoryValues()->create(['category_id' => $c['level']->id, 'category_option_id' => $c['bb']->id]);

    $this->actingAs($user)
        ->postJson(route('people.quick-update', $person), [
            'name' => 'Dana Okafor',
            'phone' => '555-0111',
        ])
        ->assertOk();

    $person->refresh();

    expect($person->phone)->toBe('555-0111')
        ->and($person->categoryValues()->count())->toBe(2);
});

test('another user can neither open nor save your person', function () {
    [$user, $c] = quickEditFixture();
    $person = Person::factory()->for($user)->create(['name' => 'Marcus Hale']);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->getJson(route('people.quick-edit', $person))->assertForbidden();
    $this->actingAs($intruder)
        ->postJson(route('people.quick-update', $person), ['name' => 'Hijacked'])
        ->assertForbidden();

    expect($person->refresh()->name)->toBe('Marcus Hale');
});

test('guests cannot use the popup endpoints', function () {
    [$user] = quickEditFixture();
    $person = Person::factory()->for($user)->create();

    $this->getJson(route('people.quick-edit', $person))->assertUnauthorized();
    $this->postJson(route('people.quick-update', $person), ['name' => 'x'])->assertUnauthorized();
});
