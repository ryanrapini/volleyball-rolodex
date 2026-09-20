<?php

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
 * The assistant's duplicate guard. The model has find_people to look people up,
 * but a model that skips that step must not be able to create a second "Marcus
 * Hale" — so create_person itself refuses anything that looks like an existing
 * person until the owner confirms it is somebody new.
 */

beforeEach(function () {
    config()->set('services.openai.key', 'test-key');
    config()->set('services.openai.model', 'test-model');
});

/**
 * Run one create attempt and hand back the tool result the model would see.
 *
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function createAttempt(User $user, array $arguments): array
{
    Http::fake([
        '*' => Http::sequence()
            ->push(completion([
                'content' => null,
                'tool_calls' => [toolCall('create_person', $arguments)],
            ]))
            ->push(completion(['content' => 'Understood.'])),
    ]);

    test()->actingAs($user)->postJson(route('ai.chat'), [
        'messages' => [['role' => 'user', 'content' => 'add them']],
    ])->assertOk();

    $sent = null;
    Http::assertSent(function ($request) use (&$sent) {
        $messages = $request->data()['messages'] ?? [];
        $tool = collect($messages)->firstWhere('role', 'tool');

        if ($tool !== null) {
            $sent = json_decode($tool['content'], true);
        }

        return true;
    });

    return $sent ?? [];
}

test('an exact duplicate is refused and the existing person is offered back', function () {
    [$user] = assistantFixture();
    Person::factory()->for($user)->create(['name' => 'Marcus Hale', 'phone' => '555-0100']);

    $result = createAttempt($user, ['name' => 'marcus hale']);

    expect($user->people()->count())->toBe(1)
        ->and($result['possible_duplicates'][0]['name'])->toBe('Marcus Hale')
        ->and($result['possible_duplicates'][0]['phone'])->toBe('555-0100')
        ->and($result)->toHaveKey('retry');
});

test('a partial name is treated as a likely duplicate', function () {
    [$user] = assistantFixture();
    Person::factory()->for($user)->create(['name' => 'Marcus Hale']);

    $result = createAttempt($user, ['name' => 'Marcus']);

    expect($user->people()->count())->toBe(1)
        ->and($result['possible_duplicates'])->toHaveCount(1);
});

test('a one character slip is treated as a likely duplicate', function () {
    [$user] = assistantFixture();
    Person::factory()->for($user)->create(['name' => 'Marcus Hale']);

    $result = createAttempt($user, ['name' => 'Marcsu Hale']);

    expect($user->people()->count())->toBe(1)
        ->and($result['possible_duplicates'])->toHaveCount(1);
});

test('the same words in another order are a likely duplicate', function () {
    [$user] = assistantFixture();
    Person::factory()->for($user)->create(['name' => 'Dana Okafor']);

    $result = createAttempt($user, ['name' => 'Okafor Dana']);

    expect($user->people()->count())->toBe(1)
        ->and($result['possible_duplicates'])->toHaveCount(1);
});

test('punctuation and spacing do not disguise a duplicate', function () {
    [$user] = assistantFixture();
    Person::factory()->for($user)->create(['name' => 'Dana Okafor']);

    $result = createAttempt($user, ['name' => '  dana   OKAFOR  ']);

    expect($user->people()->count())->toBe(1)
        ->and($result)->toHaveKey('possible_duplicates');
});

test('a genuinely different person is still created', function () {
    [$user] = assistantFixture();
    Person::factory()->for($user)->create(['name' => 'Marcus Hale']);

    createAttempt($user, ['name' => 'Dana Okafor']);

    expect($user->people()->count())->toBe(2);
});

test('a confirming retry creates the second person', function () {
    [$user] = assistantFixture();
    Person::factory()->for($user)->create(['name' => 'Marcus Hale']);

    createAttempt($user, ['name' => 'Marcus Hale Jr', 'confirm_new' => true]);

    expect($user->people()->count())->toBe(2);
});

test('the owner only ever sees their own names checked', function () {
    [$user] = assistantFixture();
    Person::factory()->create(['name' => 'Marcus Hale']);

    createAttempt($user, ['name' => 'Marcus Hale']);

    // Somebody else's Marcus Hale is not this owner's problem.
    expect($user->people()->count())->toBe(1);
});

test('the system prompt tells the model to check before adding', function () {
    [$user] = assistantFixture();

    Http::fake(['*' => Http::response(completion(['content' => 'ok']))]);

    $this->actingAs($user)->postJson(route('ai.chat'), [
        'messages' => [['role' => 'user', 'content' => 'add Marcus']],
    ])->assertOk();

    Http::assertSent(function ($request) {
        $system = $request->data()['messages'][0]['content'] ?? '';

        return str_contains($system, 'find_people')
            && str_contains(mb_strtolower($system), 'duplicate');
    });
});
