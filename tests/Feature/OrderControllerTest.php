<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(['rank' => 1]));
});

test('orders index lists orders with their incoming number', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.index'))
        ->assertOk()
        ->assertSee($order->customer->phone);
});

test('orders index hides done orders unless filtered to them', function () {
    $openOrder = Order::factory()->create(['status' => 'in_route']);
    $doneOrder = Order::factory()->create(['status' => 'done']);

    $this->get(route('orders.index'))
        ->assertSee($openOrder->customer->phone)
        ->assertDontSee($doneOrder->customer->phone);

    $this->get(route('orders.index', ['status' => 'done']))
        ->assertSee($doneOrder->customer->phone)
        ->assertDontSee($openOrder->customer->phone);
});

test('creating an order creates a customer for a new number', function () {
    $this->post(route('orders.store'), ['phone' => '+15550001111', 'address' => '1 Main St'])
        ->assertRedirect(route('orders.index'));

    $customer = Customer::firstWhere('phone', '+15550001111');

    expect($customer->orders)->toHaveCount(1)
        ->first()->address->toBe('1 Main St');
});

test('creating an order reuses the customer for a known number', function () {
    $customer = Customer::factory()->create();

    $this->post(route('orders.store'), ['phone' => $customer->phone, 'address' => '2 Main St']);

    expect(Customer::count())->toBe(1)
        ->and($customer->orders()->count())->toBe(1);
});

test('an order can be updated', function () {
    $order = Order::factory()->create();

    $this->put(route('orders.update', $order), ['phone' => '+15550002222', 'address' => '3 Main St'])
        ->assertRedirect(route('orders.index'));

    $order->refresh();

    expect($order->address)->toBe('3 Main St')
        ->and($order->customer->phone)->toBe('+15550002222');
});

test('an order can be deleted', function () {
    $order = Order::factory()->create();

    $this->delete(route('orders.destroy', $order))->assertRedirect(route('orders.index'));

    $this->assertModelMissing($order);
});

test('phone and address are required', function (string $field) {
    $this->post(route('orders.store'), [])->assertSessionHasErrors($field);
})->with(['phone', 'address']);

test('new customers start with a 5 star rating', function () {
    $this->post(route('orders.store'), ['phone' => '+15550003333', 'address' => '4 Main St']);

    expect(Customer::firstWhere('phone', '+15550003333')->rating)->toBe('5.00');
});
