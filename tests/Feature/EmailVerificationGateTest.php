<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('an unverified account cannot reach the rolodex', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($user)
        ->get(route('categories.index'))
        ->assertRedirect(route('verification.notice'));
});

test('a verified account gets straight in', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('people.index'))->assertOk();
});

test('registering sends a verification email', function () {
    Notification::fake();

    $this->post(route('register'), [
        'name' => 'Ryan',
        'email' => 'ryan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    Notification::assertSentTo(User::sole(), VerifyEmail::class);
});

test('confirming the emailed link lets the account in', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($url)->assertRedirect();

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});
