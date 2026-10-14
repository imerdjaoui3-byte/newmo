<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDriverOrderStatusRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DriverOrderStatusController extends Controller
{
    /**
     * Mark one of the driver's orders as in route, no show, or done along with how it was paid and what was sold.
     * Cash payments count as paid right away; EMT payments wait for the inbox check.
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
            ]);

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
        });

        return redirect()->route('dashboard');
    }
}
