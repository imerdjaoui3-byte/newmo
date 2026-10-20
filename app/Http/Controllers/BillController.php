<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BillController extends Controller
{
    /**
     * List the drivers' end-of-day bills; drivers only see their own.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $bills = Bill::query()
            ->when($user->isDriver(), fn ($query) => $query->whereBelongsTo($user, 'driver'))
            ->with('driver')
            ->latest('bill_date')
            ->get();

        return view('bills.index', compact('bills'));
    }

    /**
     * Show everything on one driver's daily bill: each product carried, each order delivered and how it was paid.
     */
    public function show(Request $request, Bill $bill): View
    {
        $user = $request->user();

        abort_if($user->isDriver() && $bill->driver_id !== $user->id, 403);

        $bill->load('driver');
        $orders = $bill->deliveredOrders();
        $productLines = $bill->productLines($orders);
        $paymentTotals = $orders->groupBy('payment_method')->map(fn ($ordersPaidThisWay) => $ordersPaidThisWay->sum(fn (Order $order) => $order->total()));

        return view('bills.show', compact('bill', 'orders', 'productLines', 'paymentTotals'));
    }
}
