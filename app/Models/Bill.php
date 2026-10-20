<?php

namespace App\Models;

use App\Events\BillUpdated;
use Carbon\CarbonInterface;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'driver_id',
        'bill_date',
        'loaded_quantity',
        'sold_quantity',
        'returned_quantity',
        'total_amount',
        'closed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'loaded_quantity' => 'integer',
            'sold_quantity' => 'integer',
            'returned_quantity' => 'integer',
            'total_amount' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Bring the driver's bill for the given day up to date with their stock and sales, opening it on the day's first
     * load or sale and closing it once all their stock is returned, then tell the open bill pages.
     */
    public static function refreshFor(User $driver, CarbonInterface $date): ?self
    {
        $loads = $driver->driverLoads()->whereDate('load_date', $date)->get();
        $soldQuantities = $driver->soldQuantitiesOn($date);
        $bill = self::whereBelongsTo($driver, 'driver')->whereDate('bill_date', $date)->first();

        if (! $bill && $loads->isEmpty() && $soldQuantities->isEmpty()) {
            return null;
        }

        $bill ??= new self(['driver_id' => $driver->id, 'bill_date' => $date]);

        $bill->fill([
            'loaded_quantity' => $loads->sum('quantity'),
            'sold_quantity' => $soldQuantities->sum(),
            'returned_quantity' => $loads->sum('returned_quantity'),
            'total_amount' => $driver->revenueOn($date),
            'closed_at' => $loads->isNotEmpty() && $loads->every->isReturned() ? $loads->max('returned_at') : null,
        ])->save();

        BillUpdated::dispatch($bill->id, $driver->id);

        return $bill;
    }

    /**
     * Determine whether the driver returned their stock, so the day's sales are final.
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Get the driver whose day this bill closes.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the orders the driver completed on the bill's day, in the order they were delivered, with what was sold.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    public function deliveredOrders(): \Illuminate\Database\Eloquent\Collection
    {
        return Order::query()
            ->where('driver_id', $this->driver_id)
            ->whereDate('completed_at', $this->bill_date)
            ->with(['customer', 'items.product'])
            ->orderBy('completed_at')
            ->get();
    }

    /**
     * Get, for each product the driver carried or sold that day, how much was loaded, sold and returned and for how much.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Order>  $orders  the day's delivered orders
     * @return Collection<int, array{name: string, loaded: int, sold: int, returned: int, sales: float}>
     */
    public function productLines(\Illuminate\Database\Eloquent\Collection $orders): Collection
    {
        $loads = DriverLoad::query()
            ->where('driver_id', $this->driver_id)
            ->whereDate('load_date', $this->bill_date)
            ->with('product')
            ->get()
            ->keyBy('product_id');

        $items = $orders->flatMap->items->groupBy('product_id');

        return $loads->keys()->merge($items->keys())->unique()
            ->map(function (int $productId) use ($loads, $items): array {
                $load = $loads->get($productId);
                $sold = $items->get($productId, collect());

                return [
                    'name' => $load?->product->name ?? $sold->first()->product->name,
                    'loaded' => $load->quantity ?? 0,
                    'sold' => (int) $sold->sum('quantity'),
                    'returned' => $load->returned_quantity ?? 0,
                    'sales' => $sold->sum(fn (OrderItem $item) => $item->lineTotal()),
                ];
            })
            ->sortBy('name')
            ->values();
    }
}
