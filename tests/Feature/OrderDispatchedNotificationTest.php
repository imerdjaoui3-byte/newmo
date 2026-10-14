<?php

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderDispatchedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushChannel;

uses(RefreshDatabase::class);

test('the dispatch notification is a web push that opens the driver dashboard', function () {
    $driver = User::factory()->create(['rank' => 3]);
    $order = Order::factory()->create(['driver_id' => $driver->id, 'address' => '3575 Av du Parc']);
    $notification = new OrderDispatchedNotification($order);

    expect($notification->via($driver))->toBe([WebPushChannel::class])
        ->and($notification->toWebPush($driver, $notification)->toArray())
        ->title->toBe('New order dispatched')
        ->body->toBe('3575 Av du Parc')
        ->tag->toBe("order-{$order->id}")
        ->data->toBe(['url' => route('dashboard')]);
});

test('a dispatch without an address shows the customer phone', function () {
    $driver = User::factory()->create(['rank' => 3]);
    $order = Order::factory()->create(['driver_id' => $driver->id, 'address' => null]);
    $notification = new OrderDispatchedNotification($order);

    expect($notification->toWebPush($driver, $notification)->toArray()['body'])->toBe($order->customer->phone);
});
