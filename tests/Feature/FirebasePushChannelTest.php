<?php

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderDispatchedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $privateKey);

    $this->credentialsPath = tempnam(sys_get_temp_dir(), 'firebase');
    file_put_contents($this->credentialsPath, json_encode([
        'project_id' => 'test-project',
        'client_email' => 'push@test-project.iam.gserviceaccount.com',
        'private_key' => $privateKey,
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));
    config(['services.firebase.credentials' => $this->credentialsPath]);

    $this->driver = User::factory()->create(['rank' => 3]);
    $this->order = Order::factory()->create(['driver_id' => $this->driver->id, 'address' => '3575 Av du Parc']);
});

afterEach(function () {
    unlink($this->credentialsPath);
});

test('a dispatched order is pushed to every browser the driver registered', function () {
    $this->driver->deviceTokens()->createMany([['token' => 'phone-token'], ['token' => 'laptop-token']]);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/test-project/messages/1']),
    ]);

    $this->driver->notify(new OrderDispatchedNotification($this->order));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer');

    foreach (['phone-token', 'laptop-token'] as $token) {
        Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/test-project/messages:send'
            && $request->hasHeader('Authorization', 'Bearer google-access-token')
            && $request['message']['token'] === $token
            && $request['message']['notification'] === ['title' => 'New order dispatched', 'body' => '3575 Av du Parc']
            && $request['message']['webpush']['fcm_options']['link'] === route('dashboard'));
    }
});

test('tokens firebase no longer recognises are forgotten', function () {
    $this->driver->deviceTokens()->createMany([['token' => 'stale-token'], ['token' => 'live-token']]);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
        'fcm.googleapis.com/*' => fn (Request $request) => $request['message']['token'] === 'stale-token'
            ? Http::response(['error' => ['code' => 404, 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
            : Http::response(['name' => 'projects/test-project/messages/1']),
    ]);

    $this->driver->notify(new OrderDispatchedNotification($this->order));

    expect($this->driver->deviceTokens()->pluck('token')->all())->toBe(['live-token']);
});

test('a firebase outage is reported without failing the notification', function () {
    Exceptions::fake();
    $this->driver->deviceTokens()->create(['token' => 'phone-token']);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    $this->driver->notify(new OrderDispatchedNotification($this->order));

    Exceptions::assertReported(RequestException::class);
    expect($this->driver->deviceTokens()->count())->toBe(1);
});

test('nothing is sent to a driver with no registered browsers', function () {
    Http::fake();

    $this->driver->notify(new OrderDispatchedNotification($this->order));

    Http::assertNothingSent();
});
