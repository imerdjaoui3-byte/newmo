<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('drivers.tracking', function (User $user) {
    return ! $user->isDriver();
});

Broadcast::channel('orders', function (User $user) {
    return ! $user->isDriver();
});

Broadcast::channel('drivers.online', function (User $user) {
    if ($user->isDriver()) {
        return $user->driver ? ['id' => $user->id, 'driver_id' => $user->driver->id] : false;
    }

    return $user->isPending() ? false : ['id' => $user->id, 'driver_id' => null];
});
