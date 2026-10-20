<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDriverOrderStatusRequest;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class DriverOrderStatusController extends Controller
{
    /**
     * Mark one of the driver's orders as in route, no show, or done along with how it was paid and what was sold.
     * Cash payments count as paid right away; EMT payments wait for the inbox check. Each sale goes on the driver's
     * bill for the day straight away.
     * Done orders carry the driver's rating of the customer and no shows count as the lowest rating; the customer's
     * rating is the average of all of these. Dispatch staff get a push notification when the order is in route or done.
     */
    public function __invoke(UpdateDriverOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $isDone = $request->validated('status') === 'done';
        $paymentMethod = $isDone ? $request->validated('payment_method') : null;

        DB::transaction(function () use ($request, $order, $isDone, $paymentMethod): void {
            $order->update([
                'status' => $request->validated('status'),
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentMethod === 'emt' ? $request->validated('payment_reference') : null,
                'payment_verified_at' => $paymentMethod === 'cash' ? now() : null,
                'completed_at' => $isDone ? now() : null,
                'customer_rating' => match ($request->validated('status')) {
                    'done' => $request->validated('customer_rating'),
                    'no_show' => Customer::NO_SHOW_RATING,
                    default => null,
                },
            ]);

            $order->customer->refreshRating();

            if (! $isDone) {
                return;
            }

            $items = $request->validated('items');
            $prices = Product::findMany(array_column($items, 'product_id'))->pluck('price', 'id');

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $prices[$item['product_id']],
                ]);
            }

            Bill::refreshFor($order->driver, today());
        });

        if (in_array($order->status, ['in_route', 'done'], true)) {
            Notification::send(User::staff()->get(), new OrderStatusChangedNotification($order->load(['driver', 'items'])));
        }

        return redirect()->route('dashboard');
    }
}
