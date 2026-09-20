<?php

use App\Models\Person;
use App\Models\User;
use App\Support\DefaultCategories;

/*
 * Adding the same person twice by hand. The assistant already refused
 * near-duplicates; these cover the manual form, which is where it is easiest to
 * do by accident.
 */

function duplicateCheckUser(): User
{
    $user = User::factory()->create();

    DefaultCategories::seedFor($user);

    return $user;
}

function duplicateCheckPerson(User $user, array $attributes = []): Person
{
    return Person::factory()->for($user)->create($attributes);
}

test('adding a name you already have is refused with a warning', function () {
    $user = duplicateCheckUser();

    duplicateCheckPerson($user, ['name' => 'Marcus Hale', 'phone' => '555-0100']);

    $this->actingAs($user)
        ->post(route('people.store'), ['name' => 'marcus  HALE!'])
        ->assertSessionHasErrors('duplicate');

    expect($user->people()->count())->toBe(1);
});

test('the same phone number is enough to catch a duplicate', function () {
    $user = duplicateCheckUser();

    duplicateCheckPerson($user, ['name' => 'Dana Okafor', 'phone' => '(555) 020-0300']);

    $this->actingAs($user)
        ->post(route('people.store'), ['name' => 'D. Okafor', 'phone' => '5550200300'])
        ->assertSessionHasErrors('duplicate');

    expect($user->people()->count())->toBe(1);
});

test('a typo in the name is caught', function () {
    $user = duplicateCheckUser();

    duplicateCheckPerson($user, ['name' => 'Marcus Hale']);

    $this->actingAs($user)
        ->post(route('people.store'), ['name' => 'Marcsu Hale'])
        ->assertSessionHasErrors('duplicate');

    expect($user->people()->count())->toBe(1);
});

test('the same words in a different order are caught', function () {
    $user = duplicateCheckUser();

    duplicateCheckPerson($user, ['name' => 'Marcus Hale']);

    $this->actingAs($user)
        ->post(route('people.store'), ['name' => 'Hale Marcus'])
        ->assertSessionHasErrors('duplicate');

    expect($user->people()->count())->toBe(1);
});

test('a genuinely different person saves without complaint', function () {
    $user = duplicateCheckUser();

    duplicateCheckPerson($user, ['name' => 'Marcus Hale']);

    $this->actingAs($user)
        ->post(route('people.store'), ['name' => 'Spencer Dupee', 'phone' => '412-952-8506'])
        ->assertSessionHasNoErrors();

    expect($user->people()->count())->toBe(2);
});

test('confirming saves the second person anyway', function () {
    $user = duplicateCheckUser();

    duplicateCheckPerson($user, ['name' => 'Marcus Hale']);

    $this->actingAs($user)
        ->post(route('people.store'), ['name' => 'Marcus Hale', 'confirm_duplicate' => true])
        ->assertSessionHasNoErrors();

    expect($user->people()->count())->toBe(2);
});

test('editing a person does not match them against themselves', function () {
    $user = duplicateCheckUser();

    $person = duplicateCheckPerson($user, ['name' => 'Marcus Hale', 'phone' => '555-0100']);

    $this->actingAs($user)
        ->patch(route('people.update', $person), [
            'name' => 'Marcus Hale',
            'phone' => '555-0100',
            'notes' => 'Still the same person.',
        ])
        ->assertSessionHasNoErrors();

    expect($person->refresh()->notes)->toBe('Still the same person.');
});

test('renaming a person onto someone else warns', function () {
    $user = duplicateCheckUser();

    duplicateCheckPerson($user, ['name' => 'Marcus Hale']);

    $other = duplicateCheckPerson($user, ['name' => 'Dana Okafor']);

    $this->actingAs($user)
        ->patch(route('people.update', $other), ['name' => 'Marcus Hale'])
        ->assertSessionHasErrors('duplicate');

    expect($other->refresh()->name)->toBe('Dana Okafor');
});

test('a duplicate in another account is not your business', function () {
    $mine = duplicateCheckUser();
    $theirs = duplicateCheckUser();

    duplicateCheckPerson($theirs, ['name' => 'Marcus Hale', 'phone' => '555-0100']);

    $this->actingAs($mine)
        ->post(route('people.store'), ['name' => 'Marcus Hale', 'phone' => '555-0100'])
        ->assertSessionHasNoErrors();

    expect($mine->people()->count())->toBe(1);
});

test('the confirm flag is never written to the person', function () {
    $user = duplicateCheckUser();

    $this->actingAs($user)
        ->post(route('people.store'), ['name' => 'Spencer Dupee', 'confirm_duplicate' => true])
        ->assertSessionHasNoErrors();

    expect($user->people()->sole()->getAttributes())->not->toHaveKey('confirm_duplicate');
});
