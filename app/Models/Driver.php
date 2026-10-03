<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Driver extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'phone',
        'car',
        'color',
        'status',
        'gps_lat',
        'gps_lng',
        'gps_updated_at',
        'rating',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'gps_updated_at' => 'datetime',
            'rating' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user account this driver belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include drivers who are currently logged in and have a known location.
     */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->whereHas('user', fn (Builder $q) => $q->where('is_online', true))
            ->whereNotNull('gps_lat')
            ->whereNotNull('gps_lng');
    }

    /**
     * Get the driver's details as shown on the live tracking map.
     *
     * @return array{id: int, name: string, phone: ?string, car: ?string, color: ?string, status: ?string, lat: float, lng: float, updated_at: ?string}
     */
    public function toTrackingArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user->name,
            'phone' => $this->phone,
            'car' => $this->car,
            'color' => $this->color,
            'status' => $this->status,
            'lat' => (float) $this->gps_lat,
            'lng' => (float) $this->gps_lng,
            'updated_at' => $this->gps_updated_at?->diffForHumans(),
        ];
    }

    /**
     * Get the badge color variant for the driver's status.
     */
    public function statusBadgeVariant(): string
    {
        return match ($this->status) {
            'en_route' => 'info',
            'arrive' => 'warning',
            'vendu' => 'success',
            'termine' => 'secondary',
            default => 'muted',
        };
    }
}
