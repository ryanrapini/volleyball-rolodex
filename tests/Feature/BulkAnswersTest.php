<?php

use App\Models\Person;
use App\Models\User;
use App\Models\PersonCategoryValue;

/**
 * Three people, and one who is not the owner's.
 *
 * @return array{0: User, 1: array<string, mixed>, 2: array<int, Person>}
 */
function bulkFixture(): array
{
    [$user, $c] = quickEditFixture();

    $one = Person::factory()->for($user)->create(['name' => 'One']);
    $two = Person::factory()->for($user)->create(['name' => 'Two']);
    $three = Person::factory()->for($user)->create(['name' => 'Three']);

    // "Three" already plays setter; a bulk add must not drop that.
    $three->categoryValues()->create(['category_id' => $c['position']->id, 'category_option_id' => $c['setter']->id]);
    // "Two" already answered the yes/no; a bulk edit that does not mention it must leave it.
    $two->categoryValues()->create(['category_id' => $c['canSet']->id, 'value' => false]);

    return [$user, $c, [$one, $two, $three]];
}

test('answers land on everyone selected', function () {
    [$user, $c, $people] = bulkFixture();

    $this->actingAs($user)
        ->postJson(route('people.bulk-answers'), [
            'people' => array_map(fn (Person $p) => $p->id, $people),
            'answers' => [
                ['category_id' => $c['canSet']->id, 'value' => true],
                ['category_id' => $c['level']->id, 'option_id' => $c['bb']->id],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('selected', 3)
        ->assertJsonPath('updated', 3);

    foreach ($people as $person) {
        $byCategory = $person->categoryValues()->get()->keyBy('category_id');

        expect($byCategory[$c['canSet']->id]->value)->toBeTrue()
            ->and($byCategory[$c['level']->id]->category_option_id)->toBe($c['bb']->id);
    }
});

test('a pick any category is added to what each person already has', function () {
    [$user, $c, $people] = bulkFixture();

    $this->actingAs($user)
        ->postJson(route('people.bulk-answers'), [
            'people' => array_map(fn (Person $p) => $p->id, $people),
            'answers' => [
                ['category_id' => $c['position']->id, 'option_ids' => [$c['middle']->id]],
            ],
        ])
        ->assertOk();

    $positions = fn (Person $p) => $p->categoryValues()
        ->where('category_id', $c['position']->id)
        ->pluck('category_option_id')
        ->sort()
        ->values()
        ->all();

    // The one who was already a setter now has both; the others just the addition.
    expect($positions($people[2]))->toBe(collect([$c['setter']->id, $c['middle']->id])->sort()->values()->all())
        ->and($positions($people[0]))->toBe([$c['middle']->id]);
});

test('leaving an answer unset changes nothing for anyone', function () {
    [$user, $c, $people] = bulkFixture();

    $this->actingAs($user)
        ->postJson(route('people.bulk-answers'), [
            'people' => array_map(fn (Person $p) => $p->id, $people),
            // Both entries carry a category id but no value: "leave alone".
            'answers' => [
                ['category_id' => $c['canSet']->id],
                ['category_id' => $c['position']->id],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('updated', 0);

    // "Two" keeps their explicit no; "Three" keeps their setter.
    expect($people[1]->refresh()->categoryValues()->sole()->value)->toBeFalse()
        ->and($people[2]->categoryValues()->count())->toBe(1);
    expect(PersonCategoryValue::count())->toBe(2);
});

test('categories nobody answered are left untouched', function () {
    [$user, $c, $people] = bulkFixture();

    $this->actingAs($user)
        ->postJson(route('people.bulk-answers'), [
            'people' => [$people[0]->id],
            'answers' => [['category_id' => $c['level']->id, 'option_id' => $c['a']->id]],
        ])
        ->assertOk();

    $one = $people[0]->refresh();

    expect($one->categoryValues()->count())->toBe(1)
        ->and($one->categoryValues()->sole()->category_id)->toBe($c['level']->id);
});

test('someone else\'s people are ignored, not written to', function () {
    [$user, $c, $people] = bulkFixture();
    $stranger = Person::factory()->create(['name' => 'Stranger']);

    $this->actingAs($user)
        ->postJson(route('people.bulk-answers'), [
            'people' => [$people[0]->id, $stranger->id],
            'answers' => [['category_id' => $c['canSet']->id, 'value' => true]],
        ])
        ->assertOk()
        ->assertJsonPath('selected', 1)
        ->assertJsonPath('updated', 1);

    expect($people[0]->refresh()->categoryValues()->count())->toBe(1)
        ->and($stranger->refresh()->categoryValues()->count())->toBe(0);
});

test('bulk answers are validated like any other answer payload', function () {
    [$user, $c, $people] = bulkFixture();
    [, $theirs] = quickEditFixture();

    // A category that is not theirs.
    $this->actingAs($user)
        ->postJson(route('people.bulk-answers'), [
            'people' => [$people[0]->id],
            'answers' => [['category_id' => $theirs['canSet']->id, 'value' => true]],
        ])
        ->assertStatus(422);

    // A choice belonging to a different category.
    $this->actingAs($user)
        ->postJson(route('people.bulk-answers'), [
            'people' => [$people[0]->id],
            'answers' => [['category_id' => $c['level']->id, 'option_id' => $c['setter']->id]],
        ])
        ->assertStatus(422);

    expect(PersonCategoryValue::count())->toBe(2);
});

test('bulk editing needs at least one person', function () {
    [$user, $c] = quickEditFixture();

    $this->actingAs($user)
        ->postJson(route('people.bulk-answers'), [
            'people' => [],
            'answers' => [['category_id' => $c['canSet']->id, 'value' => true]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('people');
});

test('guests cannot bulk edit', function () {
    [$user, $c, $people] = bulkFixture();

    $this->postJson(route('people.bulk-answers'), [
        'people' => [$people[0]->id],
        'answers' => [['category_id' => $c['canSet']->id, 'value' => true]],
    ])->assertUnauthorized();
});
