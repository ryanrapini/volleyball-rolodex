<?php

use App\Models\User;
use Illuminate\Foundation\Application;

/*
 * The public page is the one place an anonymous visitor can look at, so it must
 * not name the framework or the runtime. Versions only help someone fingerprint
 * the stack, and nothing here needs them.
 */

test('the landing page renders', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

test('the landing page never advertises the stack', function () {
    $response = $this->get('/');

    $response->assertOk();

    $props = $response->inertiaPage()['props'];

    expect($props)->not->toHaveKey('laravelVersion')
        ->and($props)->not->toHaveKey('phpVersion');

    $html = $response->getContent();

    expect($html)->not->toContain(Application::VERSION)
        ->and($html)->not->toContain(PHP_VERSION);
});

test('a signed-in visitor is offered their rolodex', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('auth.user.id', $user->id)
        );
});
