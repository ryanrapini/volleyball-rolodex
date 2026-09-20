<?php

use App\Models\Person;
use App\Models\User;
use App\Support\PersonPhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(PersonPhotos::DISK);
});

test('a photo can be uploaded while adding a person', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Dana Okafor',
            'photo' => UploadedFile::fake()->image('dana.jpg', 400, 400),
        ])
        ->assertRedirect();

    $person = $user->people()->sole();

    expect($person->photo_path)->not->toBeNull();
    Storage::disk(PersonPhotos::DISK)->assertExists($person->photo_path);
});

test('anything that is not an image is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Dana Okafor',
            'photo' => UploadedFile::fake()->create('sheet.pdf', 20, 'application/pdf'),
        ])
        ->assertSessionHasErrors('photo');

    expect($user->people()->count())->toBe(0);
});

test('uploading a replacement photo removes the old file', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create();

    $this->actingAs($user)->patch(route('people.update', $person), [
        'name' => $person->name,
        'photo' => UploadedFile::fake()->image('first.jpg'),
    ]);

    $first = $person->refresh()->photo_path;

    $this->actingAs($user)->patch(route('people.update', $person), [
        'name' => $person->name,
        'photo' => UploadedFile::fake()->image('second.jpg'),
    ]);

    $second = $person->refresh()->photo_path;

    expect($second)->not->toBe($first);
    Storage::disk(PersonPhotos::DISK)->assertMissing($first);
    Storage::disk(PersonPhotos::DISK)->assertExists($second);
});

test('a photo can be removed without touching the rest of the record', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create(['notes' => 'Keeps their notes.']);

    $this->actingAs($user)->patch(route('people.update', $person), [
        'name' => $person->name,
        'photo' => UploadedFile::fake()->image('shot.jpg'),
    ]);

    $path = $person->refresh()->photo_path;

    $this->actingAs($user)
        ->patch(route('people.update', $person), [
            'name' => $person->name,
            'remove_photo' => true,
        ])
        ->assertRedirect(route('people.show', $person));

    $person->refresh();

    expect($person->photo_path)->toBeNull()
        ->and($person->notes)->toBe('Keeps their notes.');

    Storage::disk(PersonPhotos::DISK)->assertMissing($path);
});

test('deleting a person deletes their photo', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create();

    $this->actingAs($user)->patch(route('people.update', $person), [
        'name' => $person->name,
        'photo' => UploadedFile::fake()->image('shot.jpg'),
    ]);

    $path = $person->refresh()->photo_path;

    $this->actingAs($user)->delete(route('people.destroy', $person));

    Storage::disk(PersonPhotos::DISK)->assertMissing($path);
});

test('the photo url reaches the index, detail and edit pages', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create();

    $this->actingAs($user)->patch(route('people.update', $person), [
        'name' => $person->name,
        'photo' => UploadedFile::fake()->image('shot.jpg'),
    ]);

    $url = PersonPhotos::url($person->refresh()->photo_path);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page->where('people.data.0.photo_url', $url)->etc());

    $this->actingAs($user)
        ->get(route('people.show', $person))
        ->assertInertia(fn (Assert $page) => $page->where('person.photo_url', $url)->etc());

    $this->actingAs($user)
        ->get(route('people.edit', $person))
        ->assertInertia(fn (Assert $page) => $page->where('person.photo_url', $url)->etc());
});

test('another user cannot attach a photo to your person', function () {
    $owner = User::factory()->create();
    $person = Person::factory()->for($owner)->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(route('people.update', $person), [
            'name' => 'Hijacked',
            'photo' => UploadedFile::fake()->image('shot.jpg'),
        ])
        ->assertForbidden();

    expect($person->refresh()->photo_path)->toBeNull();
});
