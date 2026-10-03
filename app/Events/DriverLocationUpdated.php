<?php

namespace App\Events;

use App\Models\Driver;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a driver's new position to the live tracking map.
 *
 * Broadcasts immediately (no queue worker needed) and is rescued, so a Reverb
 * outage never fails the driver's location update.
 */
class DriverLocationUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Driver $driver) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('drivers.tracking'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array{driver: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return ['driver' => $this->driver->toTrackingArray()];
    }
}
