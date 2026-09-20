<?php

use App\Models\Person;
use App\Models\User;

/*
 * New accounts have no AI until the owner switches it on, and the owner is the
 * only one who can get into the account list.
 */

function adminUser(): User
{
    return User::factory()->admin()->create();
}

function aiApprovedUser(): User
{
    return User::factory()->aiApproved()->create();
}

test('a new account is refused by the AI endpoint', function () {
    $user = User::factory()->create();

    expect($user->canUseAi())->toBeFalse();

    $this->actingAs($user)
        ->postJson(route('ai.chat'), ['messages' => []])
        ->assertForbidden();
});

test('the refusal is the gate, not validation', function () {
    // An approved account with a nonsense payload gets as far as validation;
    // a pending one never does.
    $approved = aiApprovedUser();

    $this->actingAs($approved)
        ->postJson(route('ai.chat'), ['messages' => 'nope'])
        ->assertStatus(422);
});

test('approving an account lets it through the gate', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('ai.chat'), ['messages' => 'nope'])->assertForbidden();

    $user->forceFill(['ai_approved_at' => now()])->save();

    $this->actingAs($user)->postJson(route('ai.chat'), ['messages' => 'nope'])->assertStatus(422);
});

test('an admin always has the AI', function () {
    expect(adminUser()->canUseAi())->toBeTrue();
});

test('a normal account cannot open the account list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.users'))
        ->assertForbidden();
});

test('a guest is sent to the login page', function () {
    $this->get(route('admin.users'))->assertRedirect(route('login'));
});

test('the admin sees every account with its counts', function () {
    $admin = adminUser();
    $other = User::factory()->create(['name' => 'Dana Okafor']);

    Person::factory()->for($other)->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users', 2)
            ->where('users.0.is_self', true)
            ->where('users.0.is_admin', true)
            ->where('users.1.name', 'Dana Okafor')
            ->where('users.1.people_count', 3)
            ->where('users.1.ai_approved', false)
        );
});

test('the admin can switch the AI on and off for an account', function () {
    $admin = adminUser();
    $other = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.ai', $other), ['approved' => true])
        ->assertSessionHasNoErrors();

    expect($other->refresh()->canUseAi())->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.users.ai', $other), ['approved' => false])
        ->assertSessionHasNoErrors();

    expect($other->refresh()->canUseAi())->toBeFalse();
});

test('the admin cannot switch off their own AI access', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->patch(route('admin.users.ai', $admin), ['approved' => false])
        ->assertSessionHas('error');

    expect($admin->refresh()->canUseAi())->toBeTrue();
});

test('the admin cannot delete their own account', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertSessionHas('error');

    expect(User::whereKey($admin->id)->exists())->toBeTrue();
});

test('the admin can delete an account, and its rolodex goes with it', function () {
    $admin = adminUser();
    $other = User::factory()->create();

    Person::factory()->for($other)->count(2)->create();
    Person::factory()->for($admin)->create(['name' => 'Mine']);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $other))
        ->assertSessionHas('status');

    expect(User::whereKey($other->id)->exists())->toBeFalse()
        ->and(Person::count())->toBe(1);
});

test('one admin cannot delete another', function () {
    $admin = adminUser();
    $otherAdmin = adminUser();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $otherAdmin))
        ->assertSessionHas('error');

    expect(User::whereKey($otherAdmin->id)->exists())->toBeTrue();
});

test('the admin flag cannot be set through the profile form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Ryan Rapini',
            'email' => $user->email,
            'is_admin' => true,
            'ai_approved_at' => now()->toDateTimeString(),
        ]);

    $user->refresh();

    expect($user->is_admin)->toBeFalse()
        ->and($user->ai_approved_at)->toBeNull();
});
