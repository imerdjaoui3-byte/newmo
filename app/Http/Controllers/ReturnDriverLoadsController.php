<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturnDriverLoadsRequest;
use App\Models\Bill;
use App\Models\DriverLoad;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ReturnDriverLoadsController extends Controller
{
    /**
     * Close the driver's day by moving their unsold stock back into the warehouse and closing the day's bill.
     */
    public function __invoke(ReturnDriverLoadsRequest $request): RedirectResponse
    {
        $driver = $request->user();

        DB::transaction(function () use ($driver): void {
            $soldQuantities = $driver->soldQuantitiesOn(today());

            $driver->driverLoads()
                ->whereDate('load_date', today())
                ->whereNull('returned_at')
                ->lockForUpdate()
                ->get()
                ->each(function (DriverLoad $load) use ($soldQuantities): void {
                    $leftover = $load->remaining($soldQuantities[$load->product_id] ?? 0);

                    Product::whereKey($load->product_id)->increment('quantity', $leftover);

                    $load->update(['returned_quantity' => $load->returned_quantity + $leftover, 'returned_at' => now()]);
                });

            Bill::refreshFor($driver, today());
        });

        return redirect()->route('dashboard')->with('status', 'Your leftover stock was returned to the warehouse.');
    }
}
