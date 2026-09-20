<?php

use App\Models\Category;
use App\Models\Person;
use App\Models\PersonCategoryValue;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A user with one category of each type, and the ids needed to answer them.
 *
 * @return array{0: User, 1: array<string, mixed>}
 */
function rolodexWithCategories(): array
{
    $user = User::factory()->create();

    $canSet = Category::factory()->for($user)->boolean()->create(['name' => 'Can set', 'position' => 0]);
    $level = Category::factory()->for($user)->single()->create(['name' => 'Skill level', 'position' => 1]);
    $levelA = $level->options()->create(['label' => 'A', 'position' => 0]);
    $levelBB = $level->options()->create(['label' => 'BB', 'position' => 1]);
    $position = Category::factory()->for($user)->multiple()->create(['name' => 'Position', 'position' => 2]);
    $setter = $position->options()->create(['label' => 'Setter', 'position' => 0]);
    $middle = $position->options()->create(['label' => 'Middle blocker', 'position' => 1]);

    return [$user, compact('canSet', 'level', 'levelA', 'levelBB', 'position', 'setter', 'middle')];
}

test('a yes or no answer is stored against the person', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Marcus Hale',
        'answers' => [
            ['category_id' => $c['canSet']->id, 'value' => true],
        ],
    ])->assertRedirect();

    $person = $user->people()->sole();
    $value = $person->categoryValues()->sole();

    expect($value->category_id)->toBe($c['canSet']->id)
        ->and($value->value)->toBeTrue()
        ->and($value->category_option_id)->toBeNull();
});

test('an explicit no is recorded too', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Marcus Hale',
        'answers' => [
            ['category_id' => $c['canSet']->id, 'value' => false],
        ],
    ]);

    expect($user->people()->sole()->categoryValues()->sole()->value)->toBeFalse();
});

test('a pick one answer keeps a single choice', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Marcus Hale',
        'answers' => [
            ['category_id' => $c['level']->id, 'option_id' => $c['levelBB']->id],
        ],
    ]);

    $person = $user->people()->sole();

    expect($person->categoryValues()->sole()->category_option_id)->toBe($c['levelBB']->id);

    // Answering again with a different choice replaces it.
    $this->actingAs($user)->patch(route('people.update', $person), [
        'name' => 'Marcus Hale',
        'answers' => [
            ['category_id' => $c['level']->id, 'option_id' => $c['levelA']->id],
        ],
    ]);

    $values = $person->categoryValues()->get();

    expect($values)->toHaveCount(1)
        ->and($values->first()->category_option_id)->toBe($c['levelA']->id);
});

test('a pick any answer keeps every choice', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Priya Raman',
        'answers' => [
            ['category_id' => $c['position']->id, 'option_ids' => [$c['setter']->id, $c['middle']->id]],
        ],
    ]);

    $stored = $user->people()->sole()->categoryValues()->pluck('category_option_id')->all();

    expect($stored)->toHaveCount(2)
        ->and($stored)->toContain($c['setter']->id, $c['middle']->id);
});

test('clearing an answer removes it', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Dana Okafor',
        'answers' => [
            ['category_id' => $c['canSet']->id, 'value' => true],
            ['category_id' => $c['position']->id, 'option_ids' => [$c['setter']->id]],
        ],
    ]);

    $person = $user->people()->sole();

    expect($person->categoryValues()->count())->toBe(2);

    $this->actingAs($user)->patch(route('people.update', $person), [
        'name' => 'Dana Okafor',
        'answers' => [
            ['category_id' => $c['canSet']->id, 'value' => null],
            ['category_id' => $c['position']->id, 'option_ids' => []],
        ],
    ]);

    expect($person->categoryValues()->count())->toBe(0);
});

test('a category belonging to someone else cannot be answered', function () {
    [$user, $c] = rolodexWithCategories();
    [, $theirs] = rolodexWithCategories();

    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Marcus Hale',
            'answers' => [
                ['category_id' => $theirs['canSet']->id, 'value' => true],
            ],
        ])
        ->assertSessionHasErrors('answers.0.category_id');

    expect(PersonCategoryValue::count())->toBe(0);
});

test('a choice belonging to a different category is rejected', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Marcus Hale',
            'answers' => [
                // A position option handed to the skill level category.
                ['category_id' => $c['level']->id, 'option_id' => $c['setter']->id],
            ],
        ])
        ->assertSessionHasErrors('answers.0');

    expect(PersonCategoryValue::count())->toBe(0);
});

test('the shape of the answer has to match the category', function () {
    [$user, $c] = rolodexWithCategories();

    // A yes/no sent to a choice category.
    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'One',
            'answers' => [['category_id' => $c['level']->id, 'value' => true]],
        ])
        ->assertSessionHasErrors('answers.0');

    // A choice sent to a yes/no category.
    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Two',
            'answers' => [['category_id' => $c['canSet']->id, 'option_id' => $c['setter']->id]],
        ])
        ->assertSessionHasErrors('answers.0');

    // Two choices sent to a pick-one category.
    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Three',
            'answers' => [
                ['category_id' => $c['level']->id, 'option_ids' => [$c['levelA']->id, $c['levelBB']->id]],
            ],
        ])
        ->assertSessionHasErrors('answers.0');

    expect(PersonCategoryValue::count())->toBe(0);
});

test('the same category cannot be answered twice in one submission', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Marcus Hale',
            'answers' => [
                ['category_id' => $c['canSet']->id, 'value' => true],
                ['category_id' => $c['canSet']->id, 'value' => false],
            ],
        ])
        ->assertSessionHasErrors('answers.1.category_id');
});

test('the edit form round-trips the recorded answers', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Priya Raman',
        'answers' => [
            ['category_id' => $c['canSet']->id, 'value' => true],
            ['category_id' => $c['level']->id, 'option_id' => $c['levelBB']->id],
            ['category_id' => $c['position']->id, 'option_ids' => [$c['setter']->id]],
        ],
    ]);

    $person = $user->people()->sole();

    $this->actingAs($user)
        ->get(route('people.edit', $person))
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Edit')
            ->has('categories', 3)
            ->where("answers.{$c['canSet']->id}.value", true)
            ->where("answers.{$c['level']->id}.option_id", $c['levelBB']->id)
            ->where("answers.{$c['position']->id}.option_ids", [$c['setter']->id])
            ->where("answers.{$c['position']->id}.value", null)
            ->etc());
});

test('cards show affirmative answers and the detail page also shows no', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Marcus Hale',
        'answers' => [
            ['category_id' => $c['canSet']->id, 'value' => true],
            ['category_id' => $c['level']->id, 'option_id' => $c['levelBB']->id],
            ['category_id' => $c['position']->id, 'option_ids' => [$c['setter']->id, $c['middle']->id]],
        ],
    ]);

    $person = $user->people()->sole();

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.data.0.tags', ['Can set', 'Skill level: BB', 'Position: Setter, Middle blocker'])
            ->etc());

    $this->actingAs($user)
        ->get(route('people.show', $person))
        ->assertInertia(fn (Assert $page) => $page
            ->where('person.answer_groups.0', ['name' => 'Can set', 'type' => 'boolean', 'answer' => 'Yes'])
            ->where('person.answer_groups.1.answer', 'BB')
            ->etc());
});

test('a no stays off the card but appears on the detail page', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Marcus Hale',
        'answers' => [['category_id' => $c['canSet']->id, 'value' => false]],
    ]);

    $person = $user->people()->sole();

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page->where('people.data.0.tags', [])->etc());

    $this->actingAs($user)
        ->get(route('people.show', $person))
        ->assertInertia(fn (Assert $page) => $page
            ->where('person.answer_groups.0.answer', 'No')
            ->etc());
});

test('deleting a category removes the answers recorded against it', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Marcus Hale',
        'answers' => [['category_id' => $c['canSet']->id, 'value' => true]],
    ]);

    $this->actingAs($user)->delete(route('categories.destroy', $c['canSet']));

    expect(PersonCategoryValue::count())->toBe(0);
});

test('deleting a category choice removes just that answer', function () {
    [$user, $c] = rolodexWithCategories();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Marcus Hale',
        'answers' => [
            ['category_id' => $c['position']->id, 'option_ids' => [$c['setter']->id, $c['middle']->id]],
        ],
    ]);

    // Drop "Middle blocker" from the category, keeping "Setter".
    $this->actingAs($user)->patch(route('categories.update', $c['position']), [
        'name' => 'Position',
        'type' => 'multiple',
        'options' => ['Setter'],
    ]);

    $stored = Person::sole()->categoryValues()->pluck('category_option_id')->all();

    expect($stored)->toBe([$c['setter']->id]);
});
