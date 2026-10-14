<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a dispatcher can add stock to a product', function () {
    $product = Product::factory()->create(['quantity' => 10]);

    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->patch(route('products.stock', $product), ['adjustment' => 25])
        ->assertRedirect(route('products.index'));

    expect($product->fresh()->quantity)->toBe(35);
});

test('a dispatcher can remove stock from a product', function () {
    $product = Product::factory()->create(['quantity' => 10]);

    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->patch(route('products.stock', $product), ['adjustment' => -10]);

    expect($product->fresh()->quantity)->toBe(0);
});

test('stock cannot go below zero', function () {
    $product = Product::factory()->create(['quantity' => 3]);

    $this->actingAs(User::factory()->create(['rank' => 2]))
        ->patch(route('products.stock', $product), ['adjustment' => -4])
        ->assertSessionHasErrors('adjustment');

    expect($product->fresh()->quantity)->toBe(3);
});

test('drivers cannot adjust stock', function () {
    $product = Product::factory()->create(['quantity' => 3]);

    $this->actingAs(User::factory()->create(['rank' => 3]))
        ->patch(route('products.stock', $product), ['adjustment' => 5])
        ->assertForbidden();

    expect($product->fresh()->quantity)->toBe(3);
});
