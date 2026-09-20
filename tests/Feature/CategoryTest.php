<?php

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryOption;
use App\Models\User;
use App\Support\DefaultCategories;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are asked to log in', function () {
    $this->get(route('categories.index'))->assertRedirect(route('login'));
});

test('registering seeds the starter categories', function () {
    $this->post(route('register'), [
        'name' => 'Ryan',
        'email' => 'ryan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    $user = User::sole();

    expect($user->categories()->count())->toBe(count(DefaultCategories::all()));

    $canSet = $user->categories()->where('name', 'Can set')->sole();

    expect($canSet->type)->toBe(CategoryType::Boolean)
        ->and($canSet->options)->toHaveCount(0);

    $playsAs = $user->categories()->where('name', 'Plays as')->sole();

    expect($playsAs->type)->toBe(CategoryType::Single)
        ->and($playsAs->options->pluck('label')->all())->toBe(["Men's net", "Women's net", 'Either']);
});

test('a user sees only their own categories', function () {
    $user = User::factory()->create();
    Category::factory()->for($user)->create(['name' => 'Mine']);
    Category::factory()->create(['name' => 'Theirs']);

    $this->actingAs($user)
        ->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Categories/Index')
            ->has('categories', 1)
            ->where('categories.0.name', 'Mine')
            ->etc());
});

test('a yes or no category can be created', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Under 6 ft',
            'type' => 'boolean',
        ])
        ->assertRedirect(route('categories.index'));

    $category = $user->categories()->sole();

    expect($category->name)->toBe('Under 6 ft')
        ->and($category->type)->toBe(CategoryType::Boolean)
        ->and($category->options)->toHaveCount(0);
});

test('a pick one category keeps its choices in order', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'Skill level',
        'type' => 'single',
        'options' => ['A', 'BB', ' B ', '', 'BB'],
    ])->assertRedirect(route('categories.index'));

    $category = $user->categories()->sole();

    // Blanks dropped and duplicates collapsed, original order kept.
    expect($category->options->pluck('label')->all())->toBe(['A', 'BB', 'B']);
});

test('a choice category needs at least one choice', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Position',
            'type' => 'multiple',
            'options' => ['', '   '],
        ])
        ->assertSessionHasErrors('options');

    expect($user->categories()->count())->toBe(0);
});

test('category names are unique per user but can repeat across users', function () {
    $user = User::factory()->create();
    Category::factory()->for($user)->create(['name' => 'Can set']);

    $this->actingAs($user)
        ->post(route('categories.store'), ['name' => 'Can set', 'type' => 'boolean'])
        ->assertSessionHasErrors('name');

    $other = User::factory()->create();

    $this->actingAs($other)
        ->post(route('categories.store'), ['name' => 'Can set', 'type' => 'boolean'])
        ->assertSessionDoesntHaveErrors();

    expect($other->categories()->count())->toBe(1);
});

test('editing a category reconciles its choices without losing their identity', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->single()->create(['name' => 'Skill level']);
    $a = $category->options()->create(['label' => 'A', 'position' => 0]);
    $bb = $category->options()->create(['label' => 'BB', 'position' => 1]);

    $this->actingAs($user)
        ->patch(route('categories.update', $category), [
            'name' => 'Level',
            'type' => 'single',
            // BB dropped, C added, A kept.
            'options' => ['A', 'C'],
        ])
        ->assertRedirect(route('categories.index'));

    $category->refresh();

    expect($category->name)->toBe('Level');

    $labels = $category->options()->get()->pluck('label')->all();

    expect($labels)->toBe(['A', 'C'])
        ->and($category->options()->whereKey($a->getKey())->exists())->toBeTrue()
        ->and($category->options()->whereKey($bb->getKey())->exists())->toBeFalse();
});

test('switching a category to yes or no drops its choices', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->single()->create(['name' => 'Skill level']);
    $category->options()->create(['label' => 'A', 'position' => 0]);

    $this->actingAs($user)->patch(route('categories.update', $category), [
        'name' => 'Skill level',
        'type' => 'boolean',
        'options' => ['A'],
    ])->assertRedirect(route('categories.index'));

    expect($category->refresh()->type)->toBe(CategoryType::Boolean)
        ->and($category->options()->count())->toBe(0);
});

test('deleting a category deletes its choices', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->single()->create();
    $category->options()->create(['label' => 'A', 'position' => 0]);

    $this->actingAs($user)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'));

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    $this->assertDatabaseCount('category_options', 0);
});

test('another user cannot edit or delete your category', function () {
    $owner = User::factory()->create();
    $category = Category::factory()->for($owner)->create(['name' => 'Mine']);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get(route('categories.edit', $category))->assertForbidden();
    $this->actingAs($intruder)
        ->patch(route('categories.update', $category), ['name' => 'Hijacked', 'type' => 'boolean'])
        ->assertForbidden();
    $this->actingAs($intruder)->delete(route('categories.destroy', $category))->assertForbidden();

    expect($category->refresh()->name)->toBe('Mine');
});

test('deleting a user takes their categories with them', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->single()->create();
    $category->options()->create(['label' => 'A', 'position' => 0]);

    $user->delete();

    $this->assertDatabaseCount('categories', 0);
    $this->assertDatabaseCount('category_options', 0);
});

test('the form offers every category type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('categories.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Categories/Create')
            ->has('types', 3)
            ->where('types.0.value', 'single')
            ->where('types.1.value', 'multiple')
            ->where('types.2.value', 'boolean')
            ->etc());
});

test('a category option belongs to its category', function () {
    $category = Category::factory()->create();
    $option = CategoryOption::factory()->for($category)->create();

    expect($option->category->is($category))->toBeTrue();
});
