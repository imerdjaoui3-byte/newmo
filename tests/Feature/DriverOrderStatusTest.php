<?php

use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverLoad;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

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

    $this->patch(route('driver.orders.status', $order), ['status' => 'done', 'payment_method' => 'cash', 'customer_rating' => 5, 'items' => $this->soldItems]);

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
            'customer_rating' => 5,
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
            'customer_rating' => 5,
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
        ->patch(route('driver.orders.status', $order), ['status' => 'done', 'customer_rating' => 5, 'items' => $this->soldItems, ...$payment])
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
            'customer_rating' => 5,
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
            'customer_rating' => 5,
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

test('the customer\'s rating is the average of every rating drivers gave them', function () {
    $customer = Customer::factory()->create();
    Order::factory()->create(['customer_id' => $customer->id, 'status' => 'done', 'customer_rating' => 2]);
    $order = Order::factory()->create(['customer_id' => $customer->id, 'driver_id' => $this->driverUser->id, 'status' => 'in_route']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), [
            'status' => 'done',
            'payment_method' => 'cash',
            'customer_rating' => 5,
            'items' => $this->soldItems,
        ])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->customer_rating)->toBe(5)
        ->and($customer->fresh()->rating)->toBe('3.50');
});

test('a no show counts as the lowest rating', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id, 'driver_id' => $this->driverUser->id, 'status' => 'in_route']);

    $this->actingAs($this->driverUser)->patch(route('driver.orders.status', $order), ['status' => 'no_show']);

    expect($order->fresh()->customer_rating)->toBe(Customer::NO_SHOW_RATING)
        ->and($customer->fresh()->rating)->toBe('1.00');
});

test('marking done requires a rating from 1 to 5', function (mixed $rating) {
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'in_route']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $order), [
            'status' => 'done',
            'payment_method' => 'cash',
            'customer_rating' => $rating,
            'items' => $this->soldItems,
        ])
        ->assertSessionHasErrors('customer_rating');

    expect($order->fresh()->status)->toBe('in_route');
})->with([null, 0, 6]);

test('a customer without ratings keeps 5 stars', function () {
    $customer = Customer::factory()->create(['rating' => 3]);

    $customer->refreshRating();

    expect($customer->fresh()->rating)->toBe('5.00');
});

test('dispatch staff are notified when an order is in route and when it is done', function () {
    Notification::fake();
    $staff = collect([0, 1, 2])->map(fn (int $rank) => User::factory()->create(['rank' => $rank]));
    $otherDriver = User::factory()->create(['rank' => 3]);
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'dispatched']);

    $this->actingAs($this->driverUser)->patch(route('driver.orders.status', $order), ['status' => 'in_route']);

    Notification::assertSentTo($staff, OrderStatusChangedNotification::class);
    Notification::assertNotSentTo([$otherDriver, $this->driverUser], OrderStatusChangedNotification::class);

    $this->patch(route('driver.orders.status', $order), [
        'status' => 'done',
        'payment_method' => 'cash',
        'customer_rating' => 5,
        'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
    ]);

    Notification::assertSentToTimes($staff->first(), OrderStatusChangedNotification::class, 2);
});

test('no shows do not notify dispatch staff', function () {
    Notification::fake();
    User::factory()->create(['rank' => 2]);
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'in_route']);

    $this->actingAs($this->driverUser)->patch(route('driver.orders.status', $order), ['status' => 'no_show']);

    Notification::assertNothingSent();
});

test('the staff notifications say who is heading where, then what was collected', function () {
    $this->driverUser->update(['name' => 'John Lewis']);
    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'in_route', 'address' => '4136 Rue Saint-Denis']);
    $notification = new OrderStatusChangedNotification($order);

    expect($notification->toWebPush($this->driverUser, $notification)->toArray())
        ->title->toBe('Order on the way')
        ->body->toBe('John Lewis is heading to 4136 Rue Saint-Denis')
        ->tag->toBe("order-{$order->id}");

    $order->update(['status' => 'done', 'payment_method' => 'emt']);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 25]);
    $notification = new OrderStatusChangedNotification($order->fresh());

    expect($notification->toWebPush($this->driverUser, $notification)->toArray())
        ->title->toBe('Order delivered')
        ->body->toBe('John Lewis · 4136 Rue Saint-Denis · $50.00 by EMT')
        ->data->toBe(['url' => route('orders.index', ['status' => 'done'])]);
});
