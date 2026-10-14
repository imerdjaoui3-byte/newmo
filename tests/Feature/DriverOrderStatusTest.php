<?php

use App\Models\Driver;
use App\Models\DriverLoad;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driverUser = User::factory()->create(['rank' => 3]);

    Driver::create(['user_id' => $this->driverUser->id, 'phone' => fake()->unique()->e164PhoneNumber()]);

    $this->product = Product::factory()->create(['price' => '25.00']);
    DriverLoad::factory()->create(['driver_id' => $this->driverUser->id, 'product_id' => $this->product->id, 'quantity' => 10]);

    $this->soldItems = [['product_id' => $this->product->id, 'quantity' => 1]];
});

test('the assigned driver can move an order through in route to done', function () {
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'dispatched']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), ['status' => 'in_route'])
        ->assertRedirect(route('dashboard'));

    expect($order->fresh()->status)->toBe('in_route');

    $this->patch(route('driver.orders.status', $order), ['status' => 'done', 'payment_method' => 'cash', 'items' => $this->soldItems]);

    expect($order->fresh())
        ->status->toBe('done')
        ->payment_method->toBe('cash')
        ->payment_reference->toBeNull();
});

test('an EMT payment is saved with its transaction id and waits for the inbox check', function () {
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'in_route']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), [
            'status' => 'done',
            'payment_method' => 'emt',
            'payment_reference' => 'CA1234XYZ',
            'items' => $this->soldItems,
        ]);

    expect($order->fresh())
        ->payment_method->toBe('emt')
        ->payment_reference->toBe('CA1234XYZ')
        ->payment_verified_at->toBeNull();
});

test('a cash payment is marked paid right away and ignores any transaction id sent', function () {
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'in_route']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), [
            'status' => 'done',
            'payment_method' => 'cash',
            'payment_reference' => 'leftover',
            'items' => $this->soldItems,
        ]);

    expect($order->fresh())
        ->payment_reference->toBeNull()
        ->payment_verified_at->not->toBeNull();
});

test('marking done requires a payment method, and EMT requires a transaction id', function (array $payment, string $field) {
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'in_route']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), ['status' => 'done', 'items' => $this->soldItems, ...$payment])
        ->assertSessionHasErrors($field);

    expect($order->fresh()->status)->toBe('in_route');
})->with([
    'no payment method' => [[], 'payment_method'],
    'unknown payment method' => [['payment_method' => 'card'], 'payment_method'],
    'EMT without transaction id' => [['payment_method' => 'emt'], 'payment_reference'],
]);

test('marking done records the products sold at their current price', function () {
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'in_route']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), [
            'status' => 'done',
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
        ])
        ->assertRedirect(route('dashboard'));

    expect($order->fresh()->completed_at)->not->toBeNull()
        ->and($order->items()->sole())
        ->product_id->toBe($this->product->id)
        ->quantity->toBe(3)
        ->unit_price->toBe('25.00');
});

test('sold products are validated against the driver\'s remaining stock for today', function (array $items, string $field) {
    $earlierOrder = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'done', 'completed_at' => now()]);
    OrderItem::factory()->create(['order_id' => $earlierOrder->id, 'product_id' => $this->product->id, 'quantity' => 8]);
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'in_route']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), [
            'status' => 'done',
            'payment_method' => 'cash',
            'items' => $items,
        ])
        ->assertSessionHasErrors($field);

    expect($order->fresh()->status)->toBe('in_route')
        ->and($order->items()->count())->toBe(0);
})->with([
    'no products' => [fn () => [], 'items'],
    'more than is left' => [fn () => [['product_id' => $this->product->id, 'quantity' => 3]], 'items.0.quantity'],
    'product not loaded today' => [fn () => [['product_id' => Product::factory()->create()->id, 'quantity' => 1]], 'items.0.product_id'],
    'same product twice' => [fn () => [
        ['product_id' => $this->product->id, 'quantity' => 1],
        ['product_id' => $this->product->id, 'quantity' => 1],
    ], 'items.0.product_id'],
]);

test('the assigned driver can mark an order as a no show', function (string $from) {
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => $from]);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), ['status' => 'no_show'])
        ->assertRedirect(route('dashboard'));

    expect($order->fresh())
        ->status->toBe('no_show')
        ->payment_method->toBeNull();
})->with(['dispatched', 'in_route']);

test('done orders drop off the driver dashboard', function () {
    $open = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'in_route']);
    $done = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'done']);

    $this->actingAs($this->driverUser)
        ->get(route('dashboard'))
        ->assertSee($open->address)
        ->assertDontSee($done->address);
});

test('a driver cannot update an order assigned to someone else', function () {
    $order = Order::factory()->create(['driver_id' => User::factory()->create(['rank' => 3])->id, 'status' => 'dispatched']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), ['status' => 'done', 'payment_method' => 'cash'])
        ->assertForbidden();
});

test('a done order cannot be reopened by the driver', function () {
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'done']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), ['status' => 'in_route'])
        ->assertForbidden();
});

test('the status must be in route or done', function () {
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'dispatched']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), ['status' => 'pending'])
        ->assertSessionHasErrors('status');
});
