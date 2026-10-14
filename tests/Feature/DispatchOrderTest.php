<?php

use App\Events\OrderDispatched;
use App\Events\OrderUnassigned;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderDispatchedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createDriver(bool $isActive = true): Driver
{
    return Driver::create([
        'user_id' => User::factory()->create(['rank' => 3])->id,
        'phone' => fake()->unique()->e164PhoneNumber(),
        'is_active' => $isActive,
    ]);
}

beforeEach(function () {
    Event::fake([OrderDispatched::class, OrderUnassigned::class]);
});

test('a dispatcher can dispatch an order to an active driver', function () {
    $driver = createDriver();
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->post(route('orders.dispatch', $order), ['driver_id' => $driver->user_id])
        ->assertRedirect(route('orders.index'));

    expect($order->fresh())
        ->driver_id->toBe($driver->user_id)
        ->status->toBe('dispatched');

    Event::assertDispatched(OrderDispatched::class, fn (OrderDispatched $event) => $event->order->is($order));
});

test('the driver gets a push notification for the dispatched order', function () {
    Notification::fake();
    $driver = createDriver();
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->post(route('orders.dispatch', $order), ['driver_id' => $driver->user_id]);

    Notification::assertSentTo(
        $driver->user,
        OrderDispatchedNotification::class,
        fn (OrderDispatchedNotification $notification) => $notification->order->is($order),
    );
});

test('an order cannot be dispatched to an inactive driver', function () {
    $driver = createDriver(isActive: false);
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->post(route('orders.dispatch', $order), ['driver_id' => $driver->user_id])
        ->assertSessionHasErrors('driver_id');

    expect($order->fresh()->driver_id)->toBeNull();
    Event::assertNotDispatched(OrderDispatched::class);
});

test('drivers cannot dispatch orders', function () {
    $driver = createDriver();
    $order = Order::factory()->create();

    $this->actingAs($driver->user)
        ->post(route('orders.dispatch', $order), ['driver_id' => $driver->user_id])
        ->assertForbidden();
});

test('the event broadcasts to the assigned driver only', function () {
    $driver = createDriver();
    $order = Order::factory()->create(['driver_id' => $driver->user_id]);

    expect((new OrderDispatched($order))->broadcastOn()[0]->name)
        ->toBe('private-App.Models.User.'.$driver->user_id);
});

test('drivers see their dispatched orders on the dashboard', function () {
    $driver = createDriver();
    $mine = Order::factory()->create(['driver_id' => $driver->user_id, 'status' => 'dispatched']);
    $notMine = Order::factory()->create();

    $this->actingAs($driver->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($mine->address)
        ->assertDontSee($notMine->address);
});

test('a dispatcher can cancel a driver assignment, putting the order back to pending', function (string $status) {
    $driver = createDriver();
    $order = Order::factory()->create(['driver_id' => $driver->user_id, 'status' => $status]);

    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->from(route('orders.index'))
        ->post(route('orders.unassign', $order))
        ->assertRedirect(route('orders.index'));

    expect($order->fresh())
        ->driver_id->toBeNull()
        ->status->toBe('pending');

    Event::assertDispatched(OrderUnassigned::class, fn (OrderUnassigned $event) => $event->order->is($order)
        && $event->broadcastOn()[0]->name === "private-App.Models.User.{$driver->user_id}");
})->with(['dispatched', 'in_route']);

test('finished orders keep their driver', function (string $status) {
    $driver = createDriver();
    $order = Order::factory()->create(['driver_id' => $driver->user_id, 'status' => $status]);

    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->post(route('orders.unassign', $order))
        ->assertSessionHasErrors('driver_id');

    expect($order->fresh()->driver_id)->toBe($driver->user_id);
    Event::assertNotDispatched(OrderUnassigned::class);
})->with(['done', 'no_show']);

test('drivers cannot cancel assignments', function () {
    $driver = createDriver();
    $order = Order::factory()->create(['driver_id' => $driver->user_id, 'status' => 'dispatched']);

    $this->actingAs($driver->user)
        ->post(route('orders.unassign', $order))
        ->assertForbidden();

    expect($order->fresh()->driver_id)->toBe($driver->user_id);
});

test('the cancel button only shows for orders on their way', function () {
    $driver = createDriver();
    $dispatched = Order::factory()->create(['driver_id' => $driver->user_id, 'status' => 'dispatched']);
    $pending = Order::factory()->create();

    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->get(route('orders.index'))
        ->assertSee(route('orders.unassign', $dispatched))
        ->assertDontSee(route('orders.unassign', $pending));
});
