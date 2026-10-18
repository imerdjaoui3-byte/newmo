<?php

use App\Http\Controllers\DriverLocationController;
use App\Http\Controllers\OwnTracksController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->post('/driver/location', [DriverLocationController::class, 'update']);

Route::post('/owntracks', OwnTracksController::class)->middleware('throttle:120,1')->name('owntracks.store');
