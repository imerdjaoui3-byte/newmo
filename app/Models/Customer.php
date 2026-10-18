<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * The rating customers start with, before any driver has rated them.
     */
    public const DEFAULT_RATING = 5.00;

    /**
     * The rating a customer gets for an order they didn't show up for.
     */
    public const NO_SHOW_RATING = 1;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'rating',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rating' => self::DEFAULT_RATING,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
        ];
    }

    /**
     * Get the customer's initials separated by dots (e.g. "J.L"), for use in an avatar.
     */
    public function initials(): ?string
    {
        $initials = collect(preg_split('/\s+/', trim((string) $this->name)))
            ->filter()
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('.');

        return $initials ?: null;
    }

    /**
     * Recalculate the customer's rating as the average of every rating drivers gave them, back to 5 stars when unrated.
     */
    public function refreshRating(): void
    {
        $average = $this->orders()->whereNotNull('customer_rating')->avg('customer_rating');

        $this->update(['rating' => $average === null ? self::DEFAULT_RATING : round((float) $average, 2)]);
    }

    /**
     * Get the orders placed by this customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
