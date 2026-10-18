<?php

use App\Events\DriverLocationUpdated;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([DriverLocationUpdated::class]);

    $this->driverUser = User::factory()->create(['rank' => 3, 'is_online' => true, 'last_seen_at' => now()]);
    $this->driver = Driver::create([
        'user_id' => $this->driverUser->id,
        'phone' => fake()->unique()->e164PhoneNumber(),
        'gps_lat' => 45.5,
        'gps_lng' => -73.5,
        'gps_updated_at' => now(),
    ]);
});

function authorizeOnlineChannel(User $user): TestResponse
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    Broadcast::forgetDrivers();

    require base_path('routes/channels.php');

    return test()->actingAs($user)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'presence-drivers.online',
    ]);
}

test('a driver with the dashboard open is online', function () {
    expect(Driver::online()->pluck('id')->all())->toBe([$this->driver->id]);
});

test('a logged in driver goes offline once the dashboard stops pinging', function () {
    $this->driverUser->update(['last_seen_at' => now()->subSeconds(Driver::DASHBOARD_ONLINE_SECONDS + 1)]);

    expect(Driver::online()->count())->toBe(0);
});

test('a driver who logged out is offline right away', function () {
    $this->driverUser->update(['is_online' => false]);

    expect(Driver::online()->count())->toBe(0);
});

test('a recent position keeps a driver online only when it comes from background tracking', function () {
    $this->driverUser->update(['last_seen_at' => now()->subMinutes(2)]);

    expect(Driver::online()->count())->toBe(0);

    $this->driver->issueTrackingToken();

    expect(Driver::online()->pluck('id')->all())->toBe([$this->driver->id])
        ->and($this->driver->fresh()->toTrackingArray()['background'])->toBeTrue();
});

test('sharing the location from the dashboard counts as a heartbeat', function () {
    $this->freezeSecond();
    $this->driverUser->update(['is_online' => false, 'last_seen_at' => now()->subHour()]);

    $this->actingAs($this->driverUser)
        ->postJson(route('driver.location.update'), ['lat' => 45.51, 'lng' => -73.56])
        ->assertOk();

    expect($this->driverUser->fresh())
        ->is_online->toBeTrue()
        ->last_seen_at->toEqual(now());
});

test('drivers join the online channel as themselves', function () {
    $response = authorizeOnlineChannel($this->driverUser)->assertOk();

    expect(json_decode($response->json('channel_data'), true)['user_info'])
        ->toBe(['id' => $this->driverUser->id, 'driver_id' => $this->driver->id]);
});

test('dispatchers can watch the online channel', function () {
    $dispatcher = User::factory()->create(['rank' => 2]);

    $response = authorizeOnlineChannel($dispatcher)->assertOk();

    expect(json_decode($response->json('channel_data'), true)['user_info'])
        ->toBe(['id' => $dispatcher->id, 'driver_id' => null]);
});

test('pending users cannot join the online channel', function () {
    authorizeOnlineChannel(User::factory()->create(['rank' => null]))->assertForbidden();
});
