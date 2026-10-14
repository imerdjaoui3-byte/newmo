<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\PushSubscription;

uses(RefreshDatabase::class);

function pushSubscriptionPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
{
    return [
        'endpoint' => $endpoint,
        'keys' => ['p256dh' => 'BPublicKey', 'auth' => 'authSecret'],
        'contentEncoding' => 'aes128gcm',
    ];
}

test('a signed-in user subscribes their browser to push notifications', function () {
    $user = User::factory()->create(['rank' => 3]);

    $this->actingAs($user)
        ->postJson(route('push-subscriptions.store'), pushSubscriptionPayload())
        ->assertNoContent();

    expect(PushSubscription::sole())
        ->subscribable_id->toBe($user->id)
        ->endpoint->toBe('https://fcm.googleapis.com/fcm/send/abc123')
        ->public_key->toBe('BPublicKey')
        ->auth_token->toBe('authSecret');
});

test('a browser subscribed by another user moves to the current user', function () {
    $previousUser = User::factory()->create(['rank' => 2]);
    $previousUser->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc123', 'old', 'old');
    $currentUser = User::factory()->create(['rank' => 3]);

    $this->actingAs($currentUser)->postJson(route('push-subscriptions.store'), pushSubscriptionPayload());

    expect(PushSubscription::sole()->subscribable_id)->toBe($currentUser->id);
});

test('a subscription needs an https endpoint and its encryption keys', function () {
    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->postJson(route('push-subscriptions.store'), ['endpoint' => 'http://example.com'])
        ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
});

test('a user can unsubscribe one of their browsers', function () {
    $user = User::factory()->create(['rank' => 3]);
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc123', 'key', 'token');

    $this->actingAs($user)
        ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])
        ->assertNoContent();

    expect(PushSubscription::count())->toBe(0);
});

test('guests cannot subscribe', function () {
    $this->postJson(route('push-subscriptions.store'), pushSubscriptionPayload())->assertUnauthorized();

    expect(PushSubscription::count())->toBe(0);
});

test('the layout loads the push script with the app\'s own paths and vapid key', function () {
    config(['webpush.vapid.public_key' => 'test-public-key']);

    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->get(route('orders.index'))
        ->assertSee(asset('js/push-notifications.js'))
        ->assertSee('data-subscription-url="'.route('push-subscriptions.store').'"', false)
        ->assertSee('data-vapid-public-key="test-public-key"', false);
});

test('logging in with a password asks for notification permission on the next page only', function () {
    User::factory()->create(['email' => 'dispatcher@example.com', 'password' => 'secret-pass', 'rank' => 2]);

    $this->post(route('login'), ['email' => 'dispatcher@example.com', 'password' => 'secret-pass'])
        ->assertSessionHas('promptNotifications', true);

    $this->get(route('dashboard'))->assertSee('id="notificationPrompt"', false);

    $this->get(route('dashboard'))->assertDontSee('id="notificationPrompt"', false);
});

test('drivers are offered notifications on every page, others only right after login', function () {
    $this->actingAs(User::factory()->create(['rank' => 3]))
        ->get(route('dashboard'))
        ->assertSee('id="notificationPrompt"', false);

    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->get(route('dashboard'))
        ->assertDontSee('id="notificationPrompt"', false);
});
