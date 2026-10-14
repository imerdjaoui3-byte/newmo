<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\FirebasePushChannel;
use Illuminate\Notifications\Notification;

/**
 * Pushes a "new order" alert to the driver's browsers, even when the app is closed.
 */
class OrderDispatchedNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Order $order) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [FirebasePushChannel::class];
    }

    /**
     * Get the push notification shown to the driver.
     *
     * @return array{title: string, body: string, link: string}
     */
    public function toFirebasePush(object $notifiable): array
    {
        return [
            'title' => 'New order dispatched',
            'body' => $this->order->address ?? $this->order->customer->phone,
            'link' => route('dashboard'),
        ];
    }
}
