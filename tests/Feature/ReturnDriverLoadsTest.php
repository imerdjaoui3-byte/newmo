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

    $this->product = Product::factory()->create(['quantity' => 20]);
    $this->load = DriverLoad::factory()->create([
        'driver_id' => $this->driverUser->id,
        'product_id' => $this->product->id,
        'quantity' => 10,
    ]);

    $order = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'done', 'completed_at' => now()]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id, 'quantity' => 4]);
});

test('a driver returning their stock puts the unsold units back in the warehouse', function () {
    $this->actingAs($this->driverUser)
        ->post(route('driver.loads.return'))
        ->assertRedirect(route('dashboard'));

    expect($this->product->fresh()->quantity)->toBe(26)
        ->and($this->load->fresh())
        ->returned_quantity->toBe(6)
        ->returned_at->not->toBeNull();
});

test('stock cannot be returned twice', function () {
    $this->actingAs($this->driverUser)->post(route('driver.loads.return'));

    $this->post(route('driver.loads.return'))->assertSessionHasErrors('return');

    expect($this->product->fresh()->quantity)->toBe(26);
});

test('a returned driver can no longer sell', function () {
    $this->load->update(['returned_quantity' => 6, 'returned_at' => now()]);

    $openOrder = Order::factory()->create(['driver_id' => $this->driverUser->id, 'status' => 'in_route']);

    $this->actingAs($this->driverUser)
        ->patch(route('driver.orders.status', $openOrder), [
            'status' => 'done',
            'payment_method' => 'cash',
            'customer_rating' => 5,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('items.0.quantity');

    expect($this->product->fresh()->quantity)->toBe(20);
});

test('adding stock to a returned driver reopens their day and the next return updates the bill', function () {
    $this->actingAs($this->driverUser)->post(route('driver.loads.return'));

    $this->actingAs($this->dispatcher)
        ->put(route('driver-loads.update'), [
            'date' => today()->toDateString(),
            'loads' => [$this->driverUser->id => [$this->product->id => 13]],
        ])
        ->assertSessionHasNoErrors();

    expect($this->product->fresh()->quantity)->toBe(23)
        ->and($this->load->fresh())
        ->returned_at->toBeNull()
        ->remaining(4)->toBe(3);

    $this->actingAs($this->driverUser)->post(route('driver.loads.return'));

    expect($this->product->fresh()->quantity)->toBe(26)
        ->and($this->load->fresh()->returned_quantity)->toBe(9)
        ->and(Bill::sole())
        ->loaded_quantity->toBe(13)
        ->sold_quantity->toBe(4)
        ->returned_quantity->toBe(9);
});

test('a returned driver\'s load cannot drop below what they sold and returned', function () {
    $this->load->update(['returned_quantity' => 6, 'returned_at' => now()]);

    $this->actingAs($this->dispatcher)
        ->put(route('driver-loads.update'), [
            'date' => today()->toDateString(),
            'loads' => [$this->driverUser->id => [$this->product->id => 9]],
        ])
        ->assertSessionHasErrors("loads.{$this->driverUser->id}.{$this->product->id}");

    expect($this->product->fresh()->quantity)->toBe(20);
});

test('the driver stock page keeps returned drivers editable', function () {
    $this->load->update(['returned_at' => now()]);

    $this->actingAs($this->dispatcher)
        ->get(route('driver-loads.index'))
        ->assertSee('>Returned<', false)
        ->assertDontSee('disabled', false);
});

test('dispatchers cannot return a driver\'s stock', function () {
    $this->actingAs($this->dispatcher)
        ->post(route('driver.loads.return'))
        ->assertForbidden();

    expect($this->load->fresh()->returned_at)->toBeNull();
});

test('the driver dashboard shows a return button until the stock is returned', function () {
    $this->actingAs($this->driverUser)
        ->get(route('dashboard'))
        ->assertSee(route('driver.loads.return'));

    $this->load->update(['returned_at' => now()]);

    $this->get(route('dashboard'))
        ->assertDontSee(route('driver.loads.return'))
        ->assertSee('Returned to the warehouse');
});

test('the driver stock page shows which drivers have returned without a return button', function () {
    $this->load->update(['returned_at' => now()]);

    $this->actingAs($this->dispatcher)
        ->get(route('driver-loads.index'))
        ->assertSee('>Returned<', false)
        ->assertDontSee('returnForm', false);
});
