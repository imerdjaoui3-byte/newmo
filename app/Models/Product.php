<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'price',
        'quantity',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    /**
     * Get the daily loads of this product handed to drivers.
     */
    public function driverLoads(): HasMany
    {
        return $this->hasMany(DriverLoad::class);
    }

    /**
     * Get the badge color variant for the product's stock level.
     */
    public function stockBadgeVariant(): string
    {
        return match (true) {
            $this->quantity === 0 => 'danger',
            $this->quantity <= 10 => 'warning',
            default => 'success',
        };
    }
}
