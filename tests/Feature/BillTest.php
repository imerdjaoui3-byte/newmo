<?php

use App\Models\Bill;
use App\Models\Driver;
use App\Models\DriverLoad;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
