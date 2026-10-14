<?php

use App\Models\Driver;
use App\Models\DriverLoad;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loadableDriver(bool $isActive = true): Driver
{
    return Driver::create([
        'user_id' => User::factory()->create(['rank' => 3])->id,
        'phone' => fake()->unique()->e164PhoneNumber(),
        'is_active' => $isActive,
    ]);
}

beforeEach(function () {
    $this->dispatcher = User::factory()->create(['rank' => 2]);
});

test('the page lists active drivers, products and their loads for the day', function () {
    $driver = loadableDriver();
    $product = Product::factory()->create(['name' => 'Blue Dream']);
    DriverLoad::factory()->create(['driver_id' => $driver->user_id, 'product_id' => $product->id, 'quantity' => 7]);

    $this->actingAs($this->dispatcher)
        ->get(route('driver-loads.index'))
        ->assertOk()
        ->assertSee($driver->user->name)
        ->assertSee('Blue Dream')
        ->assertSee("name=\"loads[{$driver->user_id}][{$product->id}]\"", false)
        ->assertSee('value="7"', false);
});

test('loading a driver deducts the quantity from warehouse stock', function () {
    $driver = loadableDriver();
    $product = Product::factory()->create(['quantity' => 50]);

    $this->actingAs($this->dispatcher)
        ->put(route('driver-loads.update'), [
            'date' => today()->toDateString(),
            'loads' => [$driver->user_id => [$product->id => 20]],
        ])
        ->assertRedirect(route('driver-loads.index', ['date' => today()->toDateString()]));

    expect($product->fresh()->quantity)->toBe(30)
        ->and($driver->user->driverLoads()->sole())
        ->product_id->toBe($product->id)
        ->quantity->toBe(20);
});

test('changing a load only moves the difference', function (int $newQuantity, int $expectedStock, int $expectedLoads) {
    $driver = loadableDriver();
    $product = Product::factory()->create(['quantity' => 30]);
    DriverLoad::factory()->create(['driver_id' => $driver->user_id, 'product_id' => $product->id, 'quantity' => 20]);

    $this->actingAs($this->dispatcher)->put(route('driver-loads.update'), [
        'date' => today()->toDateString(),
        'loads' => [$driver->user_id => [$product->id => $newQuantity]],
    ])->assertSessionHasNoErrors();

    expect($product->fresh()->quantity)->toBe($expectedStock)
        ->and(DriverLoad::count())->toBe($expectedLoads);
})->with([
    'increase' => [25, 25, 1],
    'decrease returns stock' => [5, 45, 1],
    'zero removes the load' => [0, 50, 0],
]);

test('loads cannot exceed warehouse stock across all drivers', function () {
    $first = loadableDriver();
    $second = loadableDriver();
    $product = Product::factory()->create(['quantity' => 10]);

    $this->actingAs($this->dispatcher)
        ->put(route('driver-loads.update'), [
            'date' => today()->toDateString(),
            'loads' => [
                $first->user_id => [$product->id => 6],
                $second->user_id => [$product->id => 6],
            ],
        ])
        ->assertSessionHasErrors("loads.product.{$product->id}");

    expect($product->fresh()->quantity)->toBe(10)
        ->and(DriverLoad::count())->toBe(0);
});

test('a load cannot be lowered below what the driver already sold that day', function () {
    $driver = loadableDriver();
    $product = Product::factory()->create(['quantity' => 10]);
    DriverLoad::factory()->create(['driver_id' => $driver->user_id, 'product_id' => $product->id, 'quantity' => 5]);
    $order = Order::factory()->create(['driver_id' => $driver->user_id, 'status' => 'done', 'completed_at' => now()]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 4]);

    $this->actingAs($this->dispatcher)
        ->put(route('driver-loads.update'), [
            'date' => today()->toDateString(),
            'loads' => [$driver->user_id => [$product->id => 3]],
        ])
        ->assertSessionHasErrors("loads.{$driver->user_id}.{$product->id}");

    expect($product->fresh()->quantity)->toBe(10);
});

test('stock cannot be loaded onto inactive drivers', function () {
    $driver = loadableDriver(isActive: false);
    $product = Product::factory()->create(['quantity' => 10]);

    $this->actingAs($this->dispatcher)
        ->put(route('driver-loads.update'), [
            'date' => today()->toDateString(),
            'loads' => [$driver->user_id => [$product->id => 5]],
        ])
        ->assertSessionHasErrors('loads');

    expect($product->fresh()->quantity)->toBe(10);
});

test('drivers cannot manage driver stock', function () {
    $driver = loadableDriver();
    $product = Product::factory()->create(['quantity' => 10]);

    $this->actingAs($driver->user)->get(route('driver-loads.index'))->assertForbidden();

    $this->actingAs($driver->user)
        ->put(route('driver-loads.update'), [
            'date' => today()->toDateString(),
            'loads' => [$driver->user_id => [$product->id => 5]],
        ])
        ->assertForbidden();
});

test('drivers see their stock for today on the dashboard', function () {
    $driver = loadableDriver();
    $today = DriverLoad::factory()->for(Product::factory()->state(['name' => 'Todays Product']))
        ->create(['driver_id' => $driver->user_id]);
    DriverLoad::factory()->for(Product::factory()->state(['name' => 'Yesterdays Product']))
        ->create(['driver_id' => $driver->user_id, 'load_date' => today()->subDay()]);

    $this->actingAs($driver->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Todays Product')
        ->assertDontSee('Yesterdays Product');
});
