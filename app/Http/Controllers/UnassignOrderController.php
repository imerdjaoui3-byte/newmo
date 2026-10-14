<?php

namespace App\Http\Controllers;

use App\Events\OrderUnassigned;
use App\Http\Requests\UnassignOrderRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class UnassignOrderController extends Controller
{
    /**
     * Take the order off its driver and put it back in the pending queue.
     */
    public function __invoke(UnassignOrderRequest $request, Order $order): RedirectResponse
    {
        $driverId = $order->driver_id;

        $order->update([
            'driver_id' => null,
            'status' => 'pending',
        ]);

        OrderUnassigned::dispatch($order, $driverId);

        return back()->with('status', 'Driver assignment cancelled.');
    }
}
