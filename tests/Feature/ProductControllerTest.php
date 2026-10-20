<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(['rank' => 1]));
});

test('guests are redirected to login', function () {
    auth()->logout();

    $this->get(route('products.index'))->assertRedirect(route('login'));
});

test('prices are shown in dollars, without cents when there are none', function () {
    Product::factory()->create(['price' => 12]);
    Product::factory()->create(['price' => 7.5]);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('$12<', false)
        ->assertSee('$7.50')
        ->assertDontSee('12.00');
});

test('products index lists products', function () {
    $product = Product::factory()->create();

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee($product->name);
});

test('a product can be created', function () {
    $this->post(route('products.store'), ['name' => 'Widget', 'price' => '19.99', 'quantity' => '40'])
        ->assertRedirect(route('products.index'));

    $this->assertDatabaseHas('products', ['name' => 'Widget', 'price' => 19.99, 'quantity' => 40]);
});

test('a product can be updated', function () {
    $product = Product::factory()->create();

    $this->put(route('products.update', $product), ['name' => 'Gadget', 'price' => '5.50', 'quantity' => '12'])
        ->assertRedirect(route('products.index'));

    expect($product->fresh())->name->toBe('Gadget')->price->toBe('5.50')->quantity->toBe(12);
});

test('a product can be deleted', function () {
    $product = Product::factory()->create();

    $this->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));

    $this->assertModelMissing($product);
});

test('name, price and quantity are validated', function (array $payload, string $field) {
    $this->post(route('products.store'), $payload)->assertSessionHasErrors($field);
})->with([
    'missing name' => [['price' => '10', 'quantity' => '1'], 'name'],
    'missing price' => [['name' => 'Widget', 'quantity' => '1'], 'price'],
    'negative price' => [['name' => 'Widget', 'price' => '-1', 'quantity' => '1'], 'price'],
    'missing quantity' => [['name' => 'Widget', 'price' => '10'], 'quantity'],
    'negative quantity' => [['name' => 'Widget', 'price' => '10', 'quantity' => '-1'], 'quantity'],
    'fractional quantity' => [['name' => 'Widget', 'price' => '10', 'quantity' => '1.5'], 'quantity'],
]);
