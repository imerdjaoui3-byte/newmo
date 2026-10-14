<?php

namespace App\Models;

use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
    }

    /**
     * Get the driver whose day this bill closes.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
