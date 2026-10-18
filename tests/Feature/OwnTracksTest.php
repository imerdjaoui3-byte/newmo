<?php

use App\Events\DriverLocationUpdated;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([DriverLocationUpdated::class]);

    $this->driverUser = User::factory()->create(['email' => 'driver@example.com', 'name' => 'John Lewis', 'rank' => 3]);
    $this->driver = Driver::create(['user_id' => $this->driverUser->id, 'phone' => fake()->unique()->e164PhoneNumber()]);
    $this->token = $this->driver->issueTrackingToken();
});

function ownTracksPoint(array $overrides = []): array
{
    return ['_type' => 'location', 'lat' => 45.5231, 'lon' => -73.5817, 'tst' => now()->subSeconds(5)->timestamp, 'acc' => 12, 'batt' => 81, ...$overrides];
}

test('a location from owntracks updates the driver and the live map', function () {
    $this->freezeSecond();

    $this->withBasicAuth('driver@example.com', $this->token)
        ->postJson(route('owntracks.store'), ownTracksPoint())
        ->assertOk()
        ->assertExactJson([]);

    expect($this->driver->fresh())
        ->gps_lat->toBe('45.5231000')
        ->gps_lng->toBe('-73.5817000')
        ->gps_updated_at->toEqual(now()->subSeconds(5));

    Event::assertDispatched(DriverLocationUpdated::class, fn (DriverLocationUpdated $event) => $event->driver->is($this->driver));
});

test('points resent after being queued offline do not move the driver backwards', function () {
    $this->driver->update(['gps_lat' => 45.6, 'gps_lng' => -73.6, 'gps_updated_at' => now()]);

    $this->withBasicAuth('driver@example.com', $this->token)
        ->postJson(route('owntracks.store'), ownTracksPoint(['tst' => now()->subHour()->timestamp]))
        ->assertOk();

    expect($this->driver->fresh()->gps_lat)->toBe('45.6000000');
    Event::assertNotDispatched(DriverLocationUpdated::class);
});

test('wrong tracking credentials are refused', function (string $email, string $token) {
    $this->withBasicAuth($email, $token === 'valid' ? $this->token : $token)
        ->postJson(route('owntracks.store'), ownTracksPoint())
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Basic realm="OwnTracks"');

    expect($this->driver->fresh()->gps_lat)->toBeNull();
})->with([
    'wrong secret' => ['driver@example.com', 'not-the-token'],
    'someone else\'s email' => ['other@example.com', 'valid'],
]);

test('setting up again disconnects the previous phone', function () {
    $oldToken = $this->token;
    $this->driver->issueTrackingToken();

    $this->withBasicAuth('driver@example.com', $oldToken)
        ->postJson(route('owntracks.store'), ownTracksPoint())
        ->assertUnauthorized();
});

test('other owntracks messages and unusable points are acknowledged without changes', function (array $payload) {
    $this->withBasicAuth('driver@example.com', $this->token)
        ->postJson(route('owntracks.store'), $payload)
        ->assertOk()
        ->assertExactJson([]);

    expect($this->driver->fresh()->gps_lat)->toBeNull();
})->with([
    'region transition' => [['_type' => 'transition', 'event' => 'enter', 'lat' => 45.5, 'lon' => -73.5, 'tst' => 1]],
    'out of range point' => [ownTracksPoint(['lat' => 123])],
]);

test('a driver gets a one-tap owntracks link whose credentials work', function () {
    $response = $this->actingAs($this->driverUser)
        ->post(route('driver.tracking.setup'))
        ->assertRedirect(route('dashboard'));

    $setup = $response->getSession()->get('trackingSetup');
    $configuration = json_decode(base64_decode(rawurldecode(Str::after($setup['link'], 'inline='))), true);

    expect($setup['link'])->toStartWith('owntracks:///config?inline=')
        ->and($configuration)->toMatchArray([
            '_type' => 'configuration',
            'mode' => 3,
            'url' => route('owntracks.store'),
            'auth' => true,
            'username' => 'driver@example.com',
            'tid' => 'JL',
        ]);

    $this->withBasicAuth($configuration['username'], $configuration['password'])
        ->postJson($configuration['url'], ownTracksPoint())
        ->assertOk();

    expect($this->driver->fresh()->gps_lat)->not->toBeNull();

    $this->get(route('dashboard'))->assertSee('Open in OwnTracks');
});

test('only drivers can set up background tracking', function () {
    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->post(route('driver.tracking.setup'))
        ->assertForbidden();
});

test('drivers tracked in the background stay online on the map while their location is recent', function () {
    $this->driverUser->update(['is_online' => false]);
    $this->driver->update(['gps_lat' => 45.5, 'gps_lng' => -73.5, 'gps_updated_at' => now()->subMinutes(Driver::BACKGROUND_ONLINE_MINUTES - 1)]);

    expect(Driver::online()->pluck('id')->all())->toBe([$this->driver->id]);

    $this->driver->update(['gps_updated_at' => now()->subMinutes(Driver::BACKGROUND_ONLINE_MINUTES + 1)]);

    expect(Driver::online()->count())->toBe(0);
});
