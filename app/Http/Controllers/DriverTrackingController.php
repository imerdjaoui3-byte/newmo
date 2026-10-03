<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class DriverTrackingController extends Controller
{
    /**
     * Display the live driver tracking map.
     */
    public function index(): View
    {
        return view('drivers.tracking');
    }

    /**
     * Get the currently online drivers and their last known location, as JSON.
     */
    public function data(): JsonResponse
    {
        $drivers = Driver::online()->with('user')->get()->map(fn (Driver $driver) => $driver->toTrackingArray());

        return response()->json(['drivers' => $drivers]);
    }
}
