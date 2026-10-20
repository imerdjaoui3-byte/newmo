<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        $stats = $this->orderStats();
        $topDrivers = $this->topDrivers(now()->subDays(7));
        $bestSellers = $this->bestSellers(now()->subDays(7));
        $ordersPerDay = $this->ordersPerDay(14);
        $ordersPerHour = $this->ordersPerHour(30);

        return view('dashboard', compact('stats', 'topDrivers', 'bestSellers', 'ordersPerDay', 'ordersPerHour'));
    }

    /**
     * Get today's figures for the dashboard cards, with yesterday's for comparison.
     *
     * @return array{received_today: int, received_change: ?int, awaiting_driver: int, in_transit: int, delivered_today: int, delivered_change: ?int, revenue_today: float, revenue_change: ?int, emt_to_check: int}
     */
    private function orderStats(): array
    {
        $receivedOn = fn (CarbonInterface $day) => Order::whereDate('created_at', $day)->count();
        $deliveredOn = fn (CarbonInterface $day) => Order::where('status', 'done')->whereDate('completed_at', $day)->count();
        $revenueOn = fn (CarbonInterface $day) => (float) $this->deliveredItems()->whereDate('orders.completed_at', $day)
            ->sum(DB::raw('order_items.quantity * order_items.unit_price'));

        $receivedToday = $receivedOn(today());
        $deliveredToday = $deliveredOn(today());
        $revenueToday = $revenueOn(today());

        return [
            'received_today' => $receivedToday,
            'received_change' => $this->percentChange($receivedToday, $receivedOn(today()->subDay())),
            'awaiting_driver' => Order::where('status', 'pending')->count(),
            'in_transit' => Order::whereIn('status', Order::OPEN_STATUSES)->count(),
            'delivered_today' => $deliveredToday,
            'delivered_change' => $this->percentChange($deliveredToday, $deliveredOn(today()->subDay())),
            'revenue_today' => $revenueToday,
            'revenue_change' => $this->percentChange($revenueToday, $revenueOn(today()->subDay())),
            'emt_to_check' => Order::where('status', 'done')->where('payment_method', 'emt')->whereNull('payment_verified_at')->count(),
        ];
    }

    /**
     * Get the drivers who delivered the most orders since the given time, with what they sold.
     *
     * @return Collection<int, array{name: string, deliveries: int, sales: float}>
     */
    private function topDrivers(CarbonInterface $since, int $limit = 5): Collection
    {
        $deliveries = Order::where('status', 'done')
            ->where('completed_at', '>=', $since)
            ->whereNotNull('driver_id')
            ->groupBy('driver_id')
            ->selectRaw('driver_id, count(*) as deliveries')
            ->orderByDesc('deliveries')
            ->limit($limit)
            ->pluck('deliveries', 'driver_id');

        $sales = $this->deliveredItems()
            ->where('orders.completed_at', '>=', $since)
            ->whereIn('orders.driver_id', $deliveries->keys())
            ->groupBy('orders.driver_id')
            ->selectRaw('orders.driver_id, sum(order_items.quantity * order_items.unit_price) as sales')
            ->pluck('sales', 'driver_id');

        $names = User::whereIn('id', $deliveries->keys())->pluck('name', 'id');

        return $deliveries->map(fn (int|string $count, int $driverId) => [
            'name' => $names[$driverId] ?? 'Unknown driver',
            'deliveries' => (int) $count,
            'sales' => (float) ($sales[$driverId] ?? 0),
        ])->values();
    }

    /**
     * Get the products that sold the most units on orders delivered since the given time.
     *
     * @return Collection<int, array{name: string, units: int, revenue: float}>
     */
    private function bestSellers(CarbonInterface $since, int $limit = 5): Collection
    {
        return $this->deliveredItems()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.completed_at', '>=', $since)
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.name, sum(order_items.quantity) as units, sum(order_items.quantity * order_items.unit_price) as revenue')
            ->orderByDesc('units')
            ->limit($limit)
            ->get()
            ->map(fn (OrderItem $item) => [
                'name' => $item->name,
                'units' => (int) $item->units,
                'revenue' => (float) $item->revenue,
            ]);
    }

    /**
     * Get how many orders came in on each of the last given days, oldest first.
     *
     * @return array<string, int>
     */
    private function ordersPerDay(int $days): array
    {
        $counts = Order::where('created_at', '>=', today()->subDays($days - 1))
            ->pluck('created_at')
            ->countBy(fn (CarbonInterface $createdAt) => $createdAt->toDateString());

        return collect(range($days - 1, 0))
            ->mapWithKeys(fn (int $daysAgo) => [
                today()->subDays($daysAgo)->format('M j') => $counts[today()->subDays($daysAgo)->toDateString()] ?? 0,
            ])
            ->all();
    }

    /**
     * Get how many orders came in at each hour of the day over the last given days.
     *
     * @return array<int, int>
     */
    private function ordersPerHour(int $days): array
    {
        $counts = Order::where('created_at', '>=', today()->subDays($days - 1))
            ->pluck('created_at')
            ->countBy(fn (CarbonInterface $createdAt) => $createdAt->hour);

        return collect(range(0, 23))->mapWithKeys(fn (int $hour) => [$hour => $counts[$hour] ?? 0])->all();
    }

    /**
     * Start a query on the items of delivered orders, joined to their order.
     *
     * @return Builder<OrderItem>
     */
    private function deliveredItems(): Builder
    {
        return OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.status', 'done');
    }

    /**
     * Get the change from the previous figure as a whole percentage, or null when there is nothing to compare with.
     */
    private function percentChange(float $current, float $previous): ?int
    {
        return $previous == 0 ? null : (int) round(($current - $previous) / $previous * 100);
    }
}
