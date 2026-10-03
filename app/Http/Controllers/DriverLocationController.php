<?php

namespace App\Http\Controllers;

use App\Events\DriverLocationUpdated;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $driver = $request->user()->driver;

        if (! $driver) {
            return response()->json(['error' => 'No driver profile linked to this user.'], 404);
        }

        $driver->update([
            'gps_lat' => $request->lat,
            'gps_lng' => $request->lng,
            'gps_updated_at' => now(),
        ]);

        DriverLocationUpdated::dispatch($driver);

        return response()->json([
            'status' => 'ok',
            'lat' => $driver->gps_lat,
            'lng' => $driver->gps_lng,
            'updated_at' => $driver->gps_updated_at,
        ]);
    }
}
