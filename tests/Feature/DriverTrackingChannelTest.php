<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    Broadcast::forgetDrivers();

    require base_path('routes/channels.php');
});

function authorizeTrackingChannel(User $user): TestResponse
{
    return test()->actingAs($user)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-drivers.tracking',
    ]);
}

test('dispatchers can listen on the tracking channel', function (int $rank) {
    authorizeTrackingChannel(User::factory()->make(['id' => 1, 'rank' => $rank]))
        ->assertOk()
        ->assertJsonStructure(['auth']);
})->with(['super admin' => 1, 'dispatch' => 2]);

test('drivers cannot listen on the tracking channel', function () {
    authorizeTrackingChannel(User::factory()->make(['id' => 1, 'rank' => 3]))
        ->assertForbidden();
});
