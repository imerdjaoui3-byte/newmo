<?php

namespace App\Http\Controllers;

use App\Events\OrderDispatched;
use App\Http\Requests\DispatchOrderRequest;
use App\Models\Order;
use App\Notifications\OrderDispatchedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class DispatchOrderController extends Controller
{
    /**
     * Assign the order to a driver and notify them, live on their dashboard and with a push notification.
     * Answers with JSON when the order was dropped on a driver on the orders page.
     */
    public function __invoke(DispatchOrderRequest $request, Order $order): RedirectResponse|JsonResponse
    {
        $order->update([
            'driver_id' => $request->validated('driver_id'),
            'status' => 'dispatched',
        ]);

        OrderDispatched::dispatch($order);
        $order->driver->notify(new OrderDispatchedNotification($order));

        if ($request->expectsJson()) {
            return response()->json(['message' => "Order dispatched to {$order->driver->name}."]);
        }

        return redirect()->route('orders.index')->with('status', 'Order dispatched successfully.');
    }
}
