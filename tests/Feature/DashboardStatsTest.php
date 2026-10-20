<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(['rank' => 2]));
});

function deliveredOrder(?User $driver, array $items, mixed $completedAt = null): Order
{
    $order = Order::factory()->create([
        'status' => 'done',
        'driver_id' => $driver?->id,
        'completed_at' => $completedAt ?? now(),
        'created_at' => $completedAt ?? now(),
    ]);

    foreach ($items as [$product, $quantity, $unitPrice]) {
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => $quantity, 'unit_price' => $unitPrice]);
    }

    return $order;
}

test('the dashboard shows today\'s figures compared with yesterday', function () {
    $product = Product::factory()->create();
    Order::factory()->count(3)->create(['status' => 'pending']);
    Order::factory()->create(['status' => 'dispatched']);
    Order::factory()->create(['status' => 'in_route']);
    deliveredOrder(null, [[$product, 2, 10]]);
    Order::factory()->create(['status' => 'done', 'payment_method' => 'emt', 'payment_reference' => 'ABC', 'completed_at' => now()]);
    foreach (range(1, 4) as $i) {
        deliveredOrder(null, [[$product, 1, 10]], now()->subDay());
    }

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('stats', [
            'received_today' => 7,
            'received_change' => 75,
            'awaiting_driver' => 3,
            'in_transit' => 2,
            'delivered_today' => 2,
            'delivered_change' => -50,
            'revenue_today' => 20.0,
            'revenue_change' => -50,
            'emt_to_check' => 1,
        ])
        ->assertSee('+75%')
        ->assertSee('$20');
});

test('no change is shown when there is nothing to compare with', function () {
    Order::factory()->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats) => $stats['received_change'] === null && $stats['revenue_change'] === null)
        ->assertDontSee('title="Compared with yesterday"', false);
});

test('the dashboard ranks drivers and products over the last 7 days', function () {
    $alice = User::factory()->create(['rank' => 3, 'name' => 'Alice']);
    $bob = User::factory()->create(['rank' => 3, 'name' => 'Bob']);
    $water = Product::factory()->create(['name' => 'Water']);
    $ice = Product::factory()->create(['name' => 'Ice']);

    deliveredOrder($alice, [[$water, 5, 2]]);
    deliveredOrder($alice, [[$ice, 1, 8.5]]);
    deliveredOrder($bob, [[$water, 1, 2], [$ice, 2, 8.5]]);
    deliveredOrder($bob, [[$ice, 50, 8.5]], now()->subDays(8));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('topDrivers', fn ($drivers) => $drivers->all() === [
            ['name' => 'Alice', 'deliveries' => 2, 'sales' => 18.5],
            ['name' => 'Bob', 'deliveries' => 1, 'sales' => 19.0],
        ])
        ->assertViewHas('bestSellers', fn ($products) => $products->all() === [
            ['name' => 'Water', 'units' => 6, 'revenue' => 12.0],
            ['name' => 'Ice', 'units' => 3, 'revenue' => 25.5],
        ]);
});

test('the charts count orders per day and per hour', function () {
    $this->travelTo(today()->setTime(15, 30));
    Order::factory()->count(2)->create();
    Order::factory()->create(['created_at' => now()->subDay()->setTime(9, 0)]);
    Order::factory()->create(['created_at' => now()->subDays(40)]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('ordersPerDay', fn (array $days) => count($days) === 14
            && end($days) === 2
            && $days[today()->subDay()->format('M j')] === 1
            && array_sum($days) === 3)
        ->assertViewHas('ordersPerHour', fn (array $hours) => count($hours) === 24
            && $hours[15] === 2
            && $hours[9] === 1
            && array_sum($hours) === 3);
});
