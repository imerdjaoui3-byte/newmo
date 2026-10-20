<?php

use App\Events\BillUpdated;
use App\Models\Bill;
use App\Models\Driver;
use App\Models\DriverLoad;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->dispatcher = User::factory()->create(['rank' => 2]);
    $this->driverUser = User::factory()->create(['rank' => 3]);
    Driver::create(['user_id' => $this->driverUser->id, 'phone' => fake()->unique()->e164PhoneNumber()]);
});

test('returning stock issues a bill with the day\'s loaded, sold and returned units and the money made', function () {
    $water = Product::factory()->create(['quantity' => 20]);
    $juice = Product::factory()->create(['quantity' => 20]);
    DriverLoad::factory()->create(['driver_id' => $this->driverUser->id, 'product_id' => $water->id, 'quantity' => 10]);
    DriverLoad::factory()->create(['driver_id' => $this->driverUser->id, 'product_id' => $juice->id, 'quantity' => 5]);

    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'done', 'completed_at' => now()]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $water->id, 'quantity' => 4, 'unit_price' => 2.50]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $juice->id, 'quantity' => 1, 'unit_price' => 6]);

    $yesterdaysOrder = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'done', 'completed_at' => now()->subDay()]);
    OrderItem::factory()->create(['order_id' => $yesterdaysOrder->id, 'product_id' => $water->id, 'quantity' => 3, 'unit_price' => 2.50]);

    $this->actingAs($this->driverUser)->post(route('driver.loads.return'));

    expect(Bill::sole())
        ->driver_id->toBe($this->driverUser->id)
        ->bill_date->toDateString()->toBe(today()->toDateString())
        ->loaded_quantity->toBe(15)
        ->sold_quantity->toBe(5)
        ->returned_quantity->toBe(10)
        ->total_amount->toBe('16.00');
});

test('dispatchers see every driver\'s bills', function () {
    $otherDriver = User::factory()->create(['rank' => 3]);
    Bill::factory()->create(['driver_id' => $this->driverUser->id]);
    Bill::factory()->create(['driver_id' => $otherDriver->id]);

    $this->actingAs($this->dispatcher)
        ->get(route('bills.index'))
        ->assertOk()
        ->assertSee($this->driverUser->name)
        ->assertSee($otherDriver->name);
});

test('drivers only see their own bills', function () {
    Bill::factory()->create(['driver_id' => $this->driverUser->id, 'total_amount' => 123.45]);
    Bill::factory()->create(['total_amount' => 987.65]);

    $this->actingAs($this->driverUser)
        ->get(route('bills.index'))
        ->assertOk()
        ->assertSee('123.45')
        ->assertDontSee('987.65');
});

test('a bill shows every product and order the driver handled that day, and how it was paid', function () {
    $water = Product::factory()->create(['name' => 'Spring Water']);
    $ice = Product::factory()->create(['name' => 'Ice Bag']);
    $soda = Product::factory()->create(['name' => 'Orange Soda']);
    DriverLoad::factory()->create(['driver_id' => $this->driverUser->id, 'product_id' => $water->id, 'quantity' => 10, 'returned_quantity' => 4, 'load_date' => today()]);
    DriverLoad::factory()->create(['driver_id' => $this->driverUser->id, 'product_id' => $soda->id, 'quantity' => 3, 'returned_quantity' => 3, 'load_date' => today()]);

    $cashOrder = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'done', 'completed_at' => now(), 'payment_method' => 'cash', 'address' => '12 Main St']);
    OrderItem::factory()->create(['order_id' => $cashOrder->id, 'product_id' => $water->id, 'quantity' => 6, 'unit_price' => 2.50]);
    $emtOrder = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'done', 'completed_at' => now(), 'payment_method' => 'emt', 'payment_reference' => 'REF123']);
    OrderItem::factory()->create(['order_id' => $emtOrder->id, 'product_id' => $ice->id, 'quantity' => 2, 'unit_price' => 4]);

    $yesterdaysOrder = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'done', 'completed_at' => now()->subDay(), 'address' => '99 Old Rd']);
    $otherDriversOrder = Order::factory()->create(['status' => 'done', 'completed_at' => now(), 'address' => '7 Elsewhere Ave']);

    $bill = Bill::factory()->create(['driver_id' => $this->driverUser->id, 'bill_date' => today(), 'total_amount' => 23]);

    $this->actingAs($this->dispatcher)
        ->get(route('bills.show', $bill))
        ->assertOk()
        ->assertViewHas('productLines', fn ($lines) => $lines->all() === [
            ['name' => 'Ice Bag', 'loaded' => 0, 'sold' => 2, 'returned' => 0, 'sales' => 8.0],
            ['name' => 'Orange Soda', 'loaded' => 3, 'sold' => 0, 'returned' => 3, 'sales' => 0],
            ['name' => 'Spring Water', 'loaded' => 10, 'sold' => 6, 'returned' => 4, 'sales' => 15.0],
        ])
        ->assertViewHas('paymentTotals', fn ($totals) => $totals->all() === ['cash' => 15.0, 'emt' => 8.0])
        ->assertSee('12 Main St')
        ->assertSee('6 × Spring Water @ $2.50')
        ->assertSee('EMT · REF123')
        ->assertSee('Not verified yet')
        ->assertSee('$23')
        ->assertDontSee('99 Old Rd')
        ->assertDontSee('7 Elsewhere Ave');
});

test('drivers can open their own bill but not another driver\'s', function () {
    $ownBill = Bill::factory()->create(['driver_id' => $this->driverUser->id]);
    $otherBill = Bill::factory()->create();

    $this->actingAs($this->driverUser)->get(route('bills.show', $ownBill))->assertOk();
    $this->actingAs($this->driverUser)->get(route('bills.show', $otherBill))->assertForbidden();
});

test('each sale goes on the driver\'s bill for the day as it happens', function () {
    Event::fake([BillUpdated::class]);
    $water = Product::factory()->create(['price' => 2.50]);
    DriverLoad::factory()->create(['driver_id' => $this->driverUser->id, 'product_id' => $water->id, 'quantity' => 10]);
    [$first, $second] = Order::factory()->count(2)->create(['driver_id' => $this->driverUser->id, 'status' => 'in_route']);

    $sell = fn (Order $order, int $quantity) => $this->actingAs($this->driverUser)->patch(route('driver.orders.status', $order), [
        'status' => 'done',
        'payment_method' => 'cash',
        'customer_rating' => 5,
        'items' => [['product_id' => $water->id, 'quantity' => $quantity]],
    ]);

    $sell($first, 4);

    expect(Bill::sole())
        ->loaded_quantity->toBe(10)
        ->sold_quantity->toBe(4)
        ->returned_quantity->toBe(0)
        ->total_amount->toBe('10.00')
        ->isClosed()->toBeFalse();

    $sell($second, 2);

    expect(Bill::sole())
        ->sold_quantity->toBe(6)
        ->total_amount->toBe('15.00');

    Event::assertDispatchedTimes(BillUpdated::class, 2);
    Event::assertDispatched(BillUpdated::class, fn (BillUpdated $event) => $event->billId === Bill::sole()->id
        && $event->driverId === $this->driverUser->id);
});

test('loading a driver opens their bill, returning the stock closes it and reloading reopens it', function () {
    Event::fake([BillUpdated::class]);
    $water = Product::factory()->create(['quantity' => 50]);
    $loadDriver = fn (int $quantity) => $this->actingAs($this->dispatcher)->put(route('driver-loads.update'), [
        'date' => today()->toDateString(),
        'loads' => [$this->driverUser->id => [$water->id => $quantity]],
    ]);

    $loadDriver(10);

    expect(Bill::sole())->loaded_quantity->toBe(10)->isClosed()->toBeFalse();

    $this->actingAs($this->driverUser)->post(route('driver.loads.return'));

    expect(Bill::sole())->returned_quantity->toBe(10)->isClosed()->toBeTrue();

    $loadDriver(15);

    expect(Bill::sole())->loaded_quantity->toBe(15)->isClosed()->toBeFalse();
});

test('bills are not opened for drivers with nothing loaded or sold', function () {
    Bill::refreshFor($this->driverUser, today());

    expect(Bill::count())->toBe(0);
});
