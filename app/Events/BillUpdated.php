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
 * Tells the bill pages that a driver's daily bill changed (a sale, new stock or the day closing), so they refresh.
 *
 * Sent to the staff and to the driver whose bill it is, only once the database transaction commits, broadcast
 * immediately (no queue worker needed) and rescued, so a Reverb outage never fails the sale itself.
 */
class BillUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance.
     */
    public function __construct(public int $billId, public int $driverId) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('bills'),
            new PrivateChannel("App.Models.User.{$this->driverId}"),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array{bill: array{id: int}}
     */
    public function broadcastWith(): array
    {
        return ['bill' => ['id' => $this->billId]];
    }
}
