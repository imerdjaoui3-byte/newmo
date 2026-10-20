<?php

namespace App\Models;

use App\Events\OrderChanged;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Statuses of orders a driver still has to deliver.
     *
     * @var list<string>
     */
    public const OPEN_STATUSES = ['dispatched', 'in_route'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'driver_id',
        'address',
        'status',
        'payment_method',
        'payment_reference',
        'payment_verified_at',
        'payment_received_at',
        'completed_at',
        'customer_rating',
    ];

    /**
     * Keep the staff pages live: every saved or deleted order tells them to refresh.
     */
    protected static function booted(): void
    {
        static::saved(fn (Order $order) => OrderChanged::dispatch($order->id, $order->status ?? 'pending'));
        static::deleted(fn (Order $order) => OrderChanged::dispatch($order->id, 'deleted'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'customer_rating' => 'integer',
            'payment_verified_at' => 'datetime',
            'payment_received_at' => 'datetime',
        ];
    }

    /**
     * Get the customer who placed this order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the driver assigned to this order.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the products sold on this order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get what the customer paid for everything on this order.
     */
    public function total(): float
    {
        return $this->items->sum(fn (OrderItem $item) => $item->lineTotal());
    }

    /**
     * Determine whether the order is dispatched to a driver and not yet delivered.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Determine whether the order was delivered and paid by EMT, so its reference can be looked up in the inbox.
     */
    public function awaitsPaymentCheck(): bool
    {
        return $this->status === 'done' && $this->payment_method === 'emt' && filled($this->payment_reference);
    }

    /**
     * Get the badge color variant for the order's status.
     */
    public function statusBadgeVariant(): string
    {
        return match ($this->status) {
            'dispatched' => 'info',
            'in_route' => 'warning',
            'done' => 'success',
            'no_show' => 'danger',
            default => 'muted',
        };
    }
}
