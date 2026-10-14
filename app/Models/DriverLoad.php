<?php

namespace App\Models;

use Database\Factories\DriverLoadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverLoad extends Model
{
    /** @use HasFactory<DriverLoadFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'driver_id',
        'product_id',
        'load_date',
        'quantity',
        'returned_quantity',
        'returned_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'load_date' => 'date',
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the driver has handed the leftover stock back to the warehouse.
     */
    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }

    /**
     * Get how many units the driver still has on hand, given how many they sold.
     */
    public function remaining(int $sold): int
    {
        return $this->quantity - $sold - $this->returned_quantity;
    }

    /**
     * Get the driver this stock was loaded onto.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product that was loaded.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
