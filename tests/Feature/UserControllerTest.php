<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users index lists pending users before ranked users and hides rank 0', function () {
    $admin = User::factory()->create(['rank' => 1, 'name' => 'Ranked Admin']);
    User::factory()->create(['rank' => null, 'name' => 'Pending Person']);
    User::factory()->create(['rank' => 0, 'name' => 'Hidden Person']);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSeeInOrder(['Pending Person', 'Ranked Admin'])
        ->assertDontSee('Hidden Person');
});
