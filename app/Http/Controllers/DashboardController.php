<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(Request $request): View
    {
        if ($request->user()->isDriver()) {
            $assignedOrders = $request->user()->assignedOrders()
                ->whereIn('status', Order::OPEN_STATUSES)
                ->with('customer')
                ->latest()
                ->get();

            $todaysLoads = $request->user()->driverLoads()
                ->whereDate('load_date', today())
                ->with('product')
                ->get()
                ->sortBy('product.name');

            $soldToday = $request->user()->soldQuantitiesOn(today());

            return view('dashboard-driver', compact('assignedOrders', 'todaysLoads', 'soldToday'));
        }

        $onlineDrivers = Driver::online()->with('user')->latest('gps_updated_at')->get();

        return view('dashboard', compact('onlineDrivers'));
    }
}
