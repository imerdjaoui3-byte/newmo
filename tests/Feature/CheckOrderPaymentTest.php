<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->dispatcher = User::factory()->create(['rank' => 2]);
    $this->dispatcher->mailbox()->create(['email' => 'orders@example.com', 'access_token' => 'token', 'expires_at' => now()->addHour()]);

    $this->order = Order::factory()->create([
        'status' => 'done',
        'payment_method' => 'emt',
        'payment_reference' => 'CA1xYz9',
        'completed_at' => now(),
    ]);
});

test('checking finds when the email quoting the reference arrived without marking the order paid', function () {
    $this->freezeSecond();
    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'abc']]]),
        'gmail.googleapis.com/gmail/v1/users/me/messages/abc*' => Http::response(['internalDate' => (string) now()->subWeek()->getTimestampMs()]),
    ]);

    $this->actingAs($this->dispatcher)
        ->from(route('orders.index'))
        ->post(route('orders.check-payment', $this->order))
        ->assertRedirect(route('orders.index'))
        ->assertSessionHas('paymentCheck', ['order_id' => $this->order->id, 'found' => true, 'message' => 'Email received 1 week ago.']);

    Http::assertSent(fn ($request) => ($request['q'] ?? null) === '"CA1xYz9"');
    expect($this->order->fresh())
        ->payment_received_at->toEqual(now()->subWeek())
        ->payment_verified_at->toBeNull();
});

test('checking with no matching email reports it on the order', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['resultSizeEstimate' => 0])]);

    $this->actingAs($this->dispatcher)
        ->post(route('orders.check-payment', $this->order))
        ->assertSessionHas('paymentCheck', ['order_id' => $this->order->id, 'found' => false, 'message' => 'No email found with CA1xYz9.']);

    expect($this->order->fresh())
        ->payment_received_at->toBeNull()
        ->payment_verified_at->toBeNull();
});

test('a checked order can be marked paid', function () {
    $this->order->update(['payment_received_at' => now()->subHour()]);

    $this->actingAs($this->dispatcher)
        ->post(route('orders.mark-paid', $this->order))
        ->assertSessionHasNoErrors();

    expect($this->order->fresh()->payment_verified_at)->not->toBeNull();
});

test('an order cannot be marked paid before its email is found', function () {
    $this->actingAs($this->dispatcher)
        ->post(route('orders.mark-paid', $this->order))
        ->assertSessionHasErrors('payment');

    expect($this->order->fresh()->payment_verified_at)->toBeNull();
});

test('checking a payment requires a connected inbox', function () {
    $this->dispatcher->mailbox()->delete();
    Http::fake();

    $this->actingAs($this->dispatcher)
        ->post(route('orders.check-payment', $this->order))
        ->assertSessionHasErrors('payment');

    Http::assertNothingSent();
});

test('only delivered emt orders can be checked', function () {
    $cashOrder = Order::factory()->create(['status' => 'done', 'payment_method' => 'cash']);
    Http::fake();

    $this->actingAs($this->dispatcher)
        ->post(route('orders.check-payment', $cashOrder))
        ->assertSessionHasErrors('payment');

    Http::assertNothingSent();
});

test('drivers cannot check payments or mark them paid', function () {
    $this->actingAs(User::factory()->create(['rank' => 3]))
        ->post(route('orders.check-payment', $this->order))
        ->assertForbidden();

    $this->order->update(['payment_received_at' => now()]);

    $this->post(route('orders.mark-paid', $this->order))->assertForbidden();

    expect($this->order->fresh()->payment_verified_at)->toBeNull();
});

test('the order list shows the check result on the order\'s row', function () {
    $this->actingAs($this->dispatcher)
        ->get(route('orders.index', ['status' => 'done']))
        ->assertSee(route('orders.check-payment', $this->order))
        ->assertDontSee(route('orders.mark-paid', $this->order));

    $this->withSession(['paymentCheck' => ['order_id' => $this->order->id, 'found' => false, 'message' => 'No email found with CA1xYz9.']])
        ->get(route('orders.index', ['status' => 'done']))
        ->assertSee('No email found with CA1xYz9.');

    $this->flushSession();
    $this->order->update(['payment_received_at' => now()->subMinute()]);

    $this->get(route('orders.index', ['status' => 'done']))
        ->assertSee(route('orders.mark-paid', $this->order))
        ->assertSee('Email received 1 minute ago.')
        ->assertDontSee('>Paid', false);

    $this->order->update(['payment_verified_at' => now()]);

    $this->get(route('orders.index', ['status' => 'done']))
        ->assertDontSee(route('orders.check-payment', $this->order))
        ->assertSee('Paid · 1 minute ago');
});

test('paid cash orders show as paid without a check button', function () {
    $cashOrder = Order::factory()->create(['status' => 'done', 'payment_method' => 'cash', 'payment_verified_at' => now()]);

    $this->actingAs($this->dispatcher)
        ->get(route('orders.index', ['status' => 'done']))
        ->assertDontSee(route('orders.check-payment', $cashOrder))
        ->assertSee('>Paid<', false);
});

test('dashboards no longer link to the mailbox', function () {
    $this->actingAs($this->dispatcher)
        ->get(route('dashboard'))
        ->assertDontSee(route('mailbox.show'));

    $this->actingAs(User::factory()->create(['rank' => 3]))
        ->get(route('dashboard'))
        ->assertDontSee(route('mailbox.show'));
});
