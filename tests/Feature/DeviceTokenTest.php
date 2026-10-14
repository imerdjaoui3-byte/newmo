<?php

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a signed-in user registers their browser for push notifications', function () {
    $user = User::factory()->create(['rank' => 3]);

    $this->actingAs($user)
        ->postJson(route('device-tokens.store'), ['token' => 'fcm-token-1'])
        ->assertNoContent();

    expect(DeviceToken::sole())
        ->user_id->toBe($user->id)
        ->token->toBe('fcm-token-1');
});

test('a token already registered by another user moves to the current user', function () {
    $previousUser = User::factory()->create(['rank' => 2]);
    $previousUser->deviceTokens()->create(['token' => 'shared-browser-token']);
    $currentUser = User::factory()->create(['rank' => 3]);

    $this->actingAs($currentUser)->postJson(route('device-tokens.store'), ['token' => 'shared-browser-token']);

    expect(DeviceToken::sole()->user_id)->toBe($currentUser->id);
});

test('a token is required', function () {
    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->postJson(route('device-tokens.store'), [])
        ->assertJsonValidationErrors('token');
});

test('guests cannot register a token', function () {
    $this->postJson(route('device-tokens.store'), ['token' => 'fcm-token-1'])->assertUnauthorized();

    expect(DeviceToken::count())->toBe(0);
});

test('the layout loads the push script from the app\'s own path', function () {
    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->get(route('orders.index'))
        ->assertSee(asset('js/firebase-notifications.js'))
        ->assertSee('data-device-token-url="'.route('device-tokens.store').'"', false);
});

test('logging in with a password asks for notification permission on the next page only', function () {
    User::factory()->create(['email' => 'driver@example.com', 'password' => 'secret-pass', 'rank' => 3]);

    $this->post(route('login'), ['email' => 'driver@example.com', 'password' => 'secret-pass'])
        ->assertSessionHas('promptNotifications', true);

    $this->get(route('dashboard'))->assertSee('id="notificationPrompt"', false);

    $this->get(route('dashboard'))->assertDontSee('id="notificationPrompt"', false);
});
