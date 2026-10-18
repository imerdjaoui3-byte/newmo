<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Pushes a driver's progress on an order (in route, done) to the dispatch staff's browsers.
 */
class OrderStatusChangedNotification extends Notification
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
     * Get the push notification; it shares a tag per order so "delivered" replaces "on the way" instead of stacking.
     */
    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $isDone = $this->order->status === 'done';
        $driverName = $this->order->driver?->name ?? 'The driver';
        $destination = $this->order->address ?? "order #{$this->order->id}";

        return (new WebPushMessage)
            ->title($isDone ? 'Order delivered' : 'Order on the way')
            ->body($isDone
                ? "{$driverName} · {$destination} · {$this->paymentSummary()}"
                : "{$driverName} is heading to {$destination}")
            ->icon(asset('favicon.ico'))
            ->tag("order-{$this->order->id}")
            ->data(['url' => route('orders.index', $isDone ? ['status' => 'done'] : [])])
            ->options(['TTL' => 3600]);
    }

    /**
     * Get what the order brought in and how it was paid, e.g. "$50.00 by cash".
     */
    private function paymentSummary(): string
    {
        $total = $this->order->items->sum(fn (OrderItem $item): float => $item->quantity * (float) $item->unit_price);
        $method = $this->order->payment_method === 'emt' ? 'EMT' : 'cash';

        return '$'.number_format($total, 2)." by {$method}";
    }
}
