<?php

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('using the app keeps a user marked as seen', function () {
    $this->freezeSecond();
    $user = User::factory()->create(['rank' => 1, 'is_online' => false, 'last_seen_at' => now()->subHour()]);

    $this->actingAs($user)->get(route('users.index'))->assertOk();

    expect($user->fresh())
        ->is_online->toBeTrue()
        ->last_seen_at->toEqual(now());
});

test('a logged in user goes offline once they stop using the app', function () {
    $user = User::factory()->create(['rank' => 2, 'is_online' => true, 'last_seen_at' => now()]);

    expect($user->isOnline())->toBeTrue();

    $user->last_seen_at = now()->subMinutes(User::ONLINE_MINUTES + 1);

    expect($user->isOnline())->toBeFalse();
});

test('a user who logged out is offline right away', function () {
    $user = User::factory()->create(['rank' => 2, 'is_online' => false, 'last_seen_at' => now()]);

    expect($user->isOnline())->toBeFalse();
});

test('drivers are online only while their dashboard keeps pinging', function () {
    $user = User::factory()->create(['rank' => 3, 'is_online' => true, 'last_seen_at' => now()]);
    Driver::create(['user_id' => $user->id, 'phone' => fake()->unique()->e164PhoneNumber()]);

    expect($user->isOnline())->toBeTrue();

    $user->last_seen_at = now()->subSeconds(Driver::DASHBOARD_ONLINE_SECONDS + 1);

    expect($user->isOnline())->toBeFalse();
});

test('the users list shows who is online', function () {
    $admin = User::factory()->create(['rank' => 1]);
    User::factory()->create(['rank' => 2, 'name' => 'Away Dispatcher', 'is_online' => true, 'last_seen_at' => now()->subHour()]);

    $this->actingAs($admin)->get(route('users.index'))
        ->assertOk()
        ->assertSee('title="Online"', false)
        ->assertSee('Last seen 1 hour ago');
});

test('the drivers list shows a driver who closed the dashboard as offline', function () {
    $user = User::factory()->create(['rank' => 3, 'is_online' => true, 'last_seen_at' => now()->subMinutes(2)]);
    Driver::create(['user_id' => $user->id, 'phone' => fake()->unique()->e164PhoneNumber()]);

    $this->actingAs(User::factory()->create(['rank' => 1]))->get(route('drivers.index'))
        ->assertOk()
        ->assertSee('Last seen 2 minutes ago')
        ->assertDontSee('title="Online"', false);
});
