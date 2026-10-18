<?php

use App\Events\OrderChanged;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('creating, changing and deleting an order tells the staff pages to refresh', function () {
    Event::fake([OrderChanged::class]);

    $order = Order::factory()->create();
    $order->update(['status' => 'dispatched']);
    $order->delete();

    Event::assertDispatched(OrderChanged::class, fn (OrderChanged $event) => $event->orderId === $order->id && $event->status === 'pending');
    Event::assertDispatched(OrderChanged::class, fn (OrderChanged $event) => $event->status === 'dispatched');
    Event::assertDispatched(OrderChanged::class, fn (OrderChanged $event) => $event->status === 'deleted');
});

test('the refresh is only sent once the change is committed', function () {
    Event::fake([OrderChanged::class]);

    DB::transaction(function () {
        Order::factory()->create();

        Event::assertNotDispatched(OrderChanged::class);
    });

    Event::assertDispatched(OrderChanged::class);
});

test('order updates broadcast on the staff orders channel', function () {
    $event = new OrderChanged(7, 'done');

    expect($event->broadcastOn()[0]->name)->toBe('private-orders')
        ->and($event->broadcastWith())->toBe(['order' => ['id' => 7, 'status' => 'done']]);
});

describe('orders channel', function () {
    beforeEach(function () {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        Broadcast::forgetDrivers();

        require base_path('routes/channels.php');
    });

    test('staff can listen for order updates', function (int $rank) {
        $this->actingAs(User::factory()->make(['id' => 1, 'rank' => $rank]))
            ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-orders'])
            ->assertOk();
    })->with(['super admin' => 1, 'dispatch' => 2]);

    test('drivers cannot listen for order updates', function () {
        $this->actingAs(User::factory()->make(['id' => 1, 'rank' => 3]))
            ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-orders'])
            ->assertForbidden();
    });
});
