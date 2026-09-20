<?php

use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are asked to log in', function () {
    $this->get(route('people.index'))->assertRedirect(route('login'));
});

test('a user sees only their own people', function () {
    $user = User::factory()->create();
    Person::factory()->for($user)->create(['name' => 'Court Regular']);
    Person::factory()->create(['name' => 'A Stranger']);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Index')
            ->where('people.total', 1)
            ->where('people.data.0.name', 'Court Regular')
            ->etc());
});

test('the index shows people in name order', function () {
    $user = User::factory()->create();
    Person::factory()->for($user)->create(['name' => 'Zoe']);
    Person::factory()->for($user)->create(['name' => 'Amir']);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.data.0.name', 'Amir')
            ->where('people.data.1.name', 'Zoe')
            ->etc());
});

test('search matches name, phone, email and notes without regard to case', function () {
    $user = User::factory()->create();
    Person::factory()->for($user)->create([
        'name' => 'Dana Okafor',
        'phone' => '(555) 123-4567',
        'email' => 'dana@example.com',
        'notes' => 'Owns a BEACH net',
    ]);
    Person::factory()->for($user)->create([
        'name' => 'Someone Else',
        'phone' => '(555) 999-0000',
        'email' => 'else@example.com',
        'notes' => 'nothing relevant',
    ]);

    foreach (['dana', '123-4567', 'DANA@EXAMPLE', 'beach'] as $term) {
        $this->actingAs($user)
            ->get(route('people.index', ['q' => $term]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.total', 1)
                ->where('people.data.0.name', 'Dana Okafor')
                ->where('filters.q', $term)
                ->etc());
    }
});

test('search treats wildcards as plain text', function () {
    $user = User::factory()->create();
    Person::factory()->for($user)->create(['name' => 'Dana Okafor']);

    $this->actingAs($user)
        ->get(route('people.index', ['q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 0)->etc());
});

test('a person can be added', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Marcus Hale',
            'phone' => '555-0100',
            'email' => 'marcus@example.com',
            'notes' => 'Great setter.',
        ])
        ->assertRedirect(route('people.show', $user->people()->sole()));

    $this->assertDatabaseHas('people', [
        'user_id' => $user->id,
        'name' => 'Marcus Hale',
        'notes' => 'Great setter.',
    ]);

    expect($user->people()->count())->toBe(1);
});

test('a person can be added with only a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('people.store'), ['name' => 'Just A Name'])
        ->assertRedirect(route('people.show', $user->people()->sole()));

    $person = $user->people()->sole();

    expect($person->phone)->toBeNull()
        ->and($person->email)->toBeNull()
        ->and($person->notes)->toBeNull();
});

test('adding a person requires a name and a usable email', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('people.store'), ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['name', 'email']);

    expect($user->people()->count())->toBe(0);
});

test('a person can be updated', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create(['name' => 'Old Name']);

    $this->actingAs($user)
        ->patch(route('people.update', $person), [
            'name' => 'New Name',
            'phone' => '555-0111',
            'email' => 'new@example.com',
            'notes' => 'Updated note.',
        ])
        ->assertRedirect(route('people.show', $person));

    $person->refresh();

    expect($person->name)->toBe('New Name')
        ->and($person->notes)->toBe('Updated note.');
});

test('a person can be deleted', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('people.destroy', $person))
        ->assertRedirect(route('people.index'));

    $this->assertDatabaseMissing('people', ['id' => $person->id]);
});

test('another user cannot read, update or delete your person', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $person = Person::factory()->for($owner)->create();

    $this->actingAs($intruder)->get(route('people.show', $person))->assertForbidden();
    $this->actingAs($intruder)->get(route('people.edit', $person))->assertForbidden();
    $this->actingAs($intruder)->patch(route('people.update', $person), ['name' => 'Hijacked'])->assertForbidden();
    $this->actingAs($intruder)->delete(route('people.destroy', $person))->assertForbidden();

    expect($person->refresh()->name)->not->toBe('Hijacked');
});

test('deleting a user takes their rolodex with them', function () {
    $user = User::factory()->create();
    Person::factory()->for($user)->create();

    $user->delete();

    $this->assertDatabaseCount('people', 0);
});

test('search finds a phone number however it is punctuated', function () {
    $user = User::factory()->create();
    Person::factory()->for($user)->create([
        'name' => 'Priya Raman',
        'phone' => '(555) 999-0000',
    ]);
    Person::factory()->for($user)->create([
        'name' => 'Someone Else',
        'phone' => '555-111-2222',
    ]);

    foreach (['555-999', '5559990000', '999 0000'] as $term) {
        $this->actingAs($user)
            ->get(route('people.index', ['q' => $term]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.total', 1)
                ->where('people.data.0.name', 'Priya Raman')
                ->etc());
    }
});

test('a saved person keeps their searchable digits in step with the phone number', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create(['phone' => '(555) 123-4567']);

    expect($person->phone_digits)->toBe('5551234567');

    $person->update(['phone' => '']);

    expect($person->refresh()->phone_digits)->toBeNull();
});

test('the confirmation message is shared with the page after adding someone', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('people.store'), ['name' => 'Marcus Hale'])
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Show')
            ->where('flash.status', 'Added Marcus Hale to your rolodex.')
            ->etc());
});
