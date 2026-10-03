<?php

use App\Events\DriverLocationUpdated;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('updating a driver location broadcasts it to the tracking map', function () {
    Event::fake([DriverLocationUpdated::class]);

    $user = User::factory()->create(['rank' => 3]);
    $driver = Driver::create(['user_id' => $user->id, 'phone' => '0600000000']);

    $this->actingAs($user)
        ->postJson(route('driver.location.update'), ['lat' => 33.5731, 'lng' => -7.5898])
        ->assertOk();

    Event::assertDispatched(DriverLocationUpdated::class, function (DriverLocationUpdated $event) use ($driver) {
        $payload = $event->broadcastWith()['driver'];

        return $event->driver->is($driver)
            && $event->broadcastOn() == [new PrivateChannel('drivers.tracking')]
            && $payload['lat'] === 33.5731
            && $payload['lng'] === -7.5898;
    });
});
