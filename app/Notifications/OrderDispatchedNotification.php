<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

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
        return [WebPushChannel::class];
    }

    /**
     * Get the push notification shown to the driver; the service worker opens `data.url` when it is clicked.
     */
    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('New order dispatched')
            ->body($this->order->address ?? $this->order->customer->phone)
            ->icon(asset('favicon.ico'))
            ->tag("order-{$this->order->id}")
            ->data(['url' => route('dashboard')])
            ->options(['TTL' => 3600, 'urgency' => 'high']);
    }
}
