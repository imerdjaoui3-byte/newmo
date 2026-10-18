<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the staff pages that an order was created, changed or deleted, so they refresh their data in place.
 *
 * Sent only once the database transaction commits (so pages never fetch half-saved data), broadcast immediately
 * (no queue worker needed) and rescued, so a Reverb outage never fails the change itself.
 */
class OrderChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance.
     */
    public function __construct(public int $orderId, public string $status) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array{order: array{id: int, status: string}}
     */
    public function broadcastWith(): array
    {
        return ['order' => ['id' => $this->orderId, 'status' => $this->status]];
    }
}
