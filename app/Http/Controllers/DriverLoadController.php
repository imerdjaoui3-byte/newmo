<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDriverLoadsRequest;
use App\Models\Bill;
use App\Models\Driver;
use App\Models\DriverLoad;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DriverLoadController extends Controller
{
    /**
     * Show each active driver's start-of-day stock for the selected date.
     */
    public function index(Request $request): View
    {
        abort_if($request->user()->isDriver(), 403);

        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $date = $request->date('date') ?? today();
        $drivers = Driver::where('is_active', true)->with('user')->get()->sortBy('user.name');
        $products = Product::orderBy('name')->get();
        $driverLoads = DriverLoad::whereDate('load_date', $date)->get();
        $loads = $driverLoads->mapWithKeys(fn (DriverLoad $load): array => ["{$load->driver_id}-{$load->product_id}" => $load->quantity]);
        $returnedDriverIds = $driverLoads->filter->isReturned()->pluck('driver_id')->unique();

        return view('driver-loads.index', compact('date', 'drivers', 'products', 'loads', 'returnedDriverIds'));
    }

    /**
     * Save the drivers' stock for the day, moving the difference out of (or back into) the warehouse.
     * Changing the stock of a driver who already returned reopens their day (and bill) so they can sell and return again.
     */
    public function update(UpdateDriverLoadsRequest $request): RedirectResponse
    {
        $date = $request->validated('date');

        DB::transaction(function () use ($request, $date): void {
            $existingLoads = DriverLoad::whereDate('load_date', $date)->lockForUpdate()->get()
                ->keyBy(fn (DriverLoad $load): string => "{$load->driver_id}-{$load->product_id}");
            $changedDriverIds = [];

            foreach ($request->loads() as $driverId => $products) {
                foreach ($products as $productId => $quantity) {
                    $load = $existingLoads->get("{$driverId}-{$productId}");
                    $difference = $quantity - ($load?->quantity ?? 0);

                    if ($difference === 0) {
                        continue;
                    }

                    Product::whereKey($productId)->decrement('quantity', $difference);

                    match (true) {
                        $quantity === 0 => $load->delete(),
                        $load !== null => $load->update(['quantity' => $quantity]),
                        default => DriverLoad::create([
                            'driver_id' => $driverId,
                            'product_id' => $productId,
                            'load_date' => $date,
                            'quantity' => $quantity,
                        ]),
                    };

                    $changedDriverIds[] = $driverId;
                }
            }

            DriverLoad::whereDate('load_date', $date)
                ->whereIn('driver_id', array_unique($changedDriverIds))
                ->update(['returned_at' => null]);

            User::findMany(array_unique($changedDriverIds))
                ->each(fn (User $driver) => Bill::refreshFor($driver, Carbon::parse($date)));
        });

        return redirect()->route('driver-loads.index', ['date' => $date])->with('status', 'Driver stock saved.');
    }
}
