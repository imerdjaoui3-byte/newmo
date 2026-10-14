<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a driver an order was taken off them, so it disappears from their dashboard.
 *
 * Broadcasts immediately (no queue worker needed) and is rescued, so a Reverb
 * outage never fails the cancellation itself.
 */
class OrderUnassigned implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Order $order, public int $driverId) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->driverId),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array{order: array{id: int}}
     */
    public function broadcastWith(): array
    {
        return [
            'order' => ['id' => $this->order->id],
        ];
    }
}
