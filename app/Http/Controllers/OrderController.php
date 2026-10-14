<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display the orders still in progress, or only the done ones when filtered.
     */
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', 'in:done']]);

        $showDone = $request->query('status') === 'done';

        $orders = Order::query()
            ->when($showDone, fn ($query) => $query->where('status', 'done'), fn ($query) => $query->where('status', '!=', 'done'))
            ->with(['customer', 'driver', 'items.product'])
            ->latest()
            ->get();
        $drivers = Driver::where('is_active', true)->with('user')->get()->sortBy('user.name');

        return view('orders.index', compact('orders', 'drivers', 'showDone'));
    }

    /**
     * Show the form for creating a new order.
     */
    public function create(): View
    {
        return view('orders.create');
    }

    /**
     * Store a newly created order in storage.
     */
    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $customer = Customer::firstOrCreate(['phone' => $request->validated('phone')]);

        $customer->orders()->create(['address' => $request->validated('address')]);

        return redirect()->route('orders.index')->with('status', 'Order created successfully.');
    }

    /**
     * Show the form for editing the specified order.
     */
    public function edit(Order $order): View
    {
        $order->load('customer');

        return view('orders.edit', compact('order'));
    }

    /**
     * Update the specified order in storage.
     */
    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $customer = Customer::firstOrCreate(['phone' => $request->validated('phone')]);

        $order->update([
            'customer_id' => $customer->id,
            'address' => $request->validated('address'),
        ]);

        return redirect()->route('orders.index')->with('status', 'Order updated successfully.');
    }

    /**
     * Remove the specified order from storage.
     */
    public function destroy(Order $order): RedirectResponse
    {
        $order->delete();

        return redirect()->route('orders.index')->with('status', 'Order deleted successfully.');
    }
}
