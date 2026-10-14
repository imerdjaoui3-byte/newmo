<?php

namespace App\Http\Controllers;

use App\Events\OrderDispatched;
use App\Http\Requests\DispatchOrderRequest;
use App\Models\Order;
use App\Notifications\OrderDispatchedNotification;
use Illuminate\Http\RedirectResponse;

class DispatchOrderController extends Controller
{
    /**
     * Assign the order to a driver and notify them, live on their dashboard and with a push notification.
     */
    public function __invoke(DispatchOrderRequest $request, Order $order): RedirectResponse
    {
        $order->update([
            'driver_id' => $request->validated('driver_id'),
            'status' => 'dispatched',
        ]);

        OrderDispatched::dispatch($order);
        $order->driver->notify(new OrderDispatchedNotification($order));

        return redirect()->route('orders.index')->with('status', 'Order dispatched successfully.');
    }
}
