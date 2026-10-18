<?php

namespace App\Models;

use App\Events\DriverLocationUpdated;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Driver extends Model
{
    /**
     * How long after its last location a driver tracked in the background (OwnTracks) still counts as online.
     */
    public const BACKGROUND_ONLINE_MINUTES = 10;

    /**
     * How long after its last ping a driver sharing their location from the open dashboard still counts as online
     * (the dashboard pings every 10 seconds).
     */
    public const DASHBOARD_ONLINE_SECONDS = 30;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'tracking_token_hash',
    ];

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
     * Scope a query to drivers with a known location who are logged in with the dashboard open, or still reporting
     * it in the background. Being logged in alone is not enough: closing the browser never logs a driver out.
     */
    public function scopeOnline(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $query) => $query
                ->whereHas('user', fn (Builder $q) => $q
                    ->where('is_online', true)
                    ->where('last_seen_at', '>=', now()->subSeconds(self::DASHBOARD_ONLINE_SECONDS)))
                ->orWhere(fn (Builder $q) => $q
                    ->whereNotNull('tracking_token_hash')
                    ->where('gps_updated_at', '>=', now()->subMinutes(self::BACKGROUND_ONLINE_MINUTES))))
            ->whereNotNull('gps_lat')
            ->whereNotNull('gps_lng');
    }

    /**
     * Determine whether the background tracking app is still reporting this driver's location, so they stay online
     * after closing the dashboard.
     */
    public function isTrackedInBackground(): bool
    {
        return $this->hasTrackingApp()
            && $this->gps_updated_at?->gte(now()->subMinutes(self::BACKGROUND_ONLINE_MINUTES));
    }

    /**
     * Save a new position and show it on the live tracking map, ignoring positions older than the one already known
     * (OwnTracks resends points it queued while offline).
     */
    public function recordLocation(float $lat, float $lng, CarbonInterface $recordedAt): bool
    {
        if ($this->gps_updated_at && $recordedAt->lt($this->gps_updated_at)) {
            return false;
        }

        $this->update([
            'gps_lat' => $lat,
            'gps_lng' => $lng,
            'gps_updated_at' => $recordedAt,
        ]);

        DriverLocationUpdated::dispatch($this);

        return true;
    }

    /**
     * Create a new secret for the driver's background tracking app, replacing (and so disconnecting) any previous one.
     */
    public function issueTrackingToken(): string
    {
        $token = Str::random(40);

        $this->forceFill(['tracking_token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    /**
     * Find the driver a background tracking app signs in as, matching both the account email and the secret.
     */
    public static function findByTrackingCredentials(?string $email, ?string $token): ?self
    {
        if (! $email || ! $token) {
            return null;
        }

        $driver = self::with('user')->firstWhere('tracking_token_hash', hash('sha256', $token));

        return $driver && hash_equals($driver->user->email, $email) ? $driver : null;
    }

    /**
     * Determine whether a background tracking app has been set up for this driver.
     */
    public function hasTrackingApp(): bool
    {
        return $this->tracking_token_hash !== null;
    }

    /**
     * Get the driver's details as shown on the live tracking map.
     *
     * @return array{id: int, user_id: int, name: string, phone: ?string, car: ?string, color: ?string, status: ?string, lat: float, lng: float, updated_at: ?string, background: bool}
     */
    public function toTrackingArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user->name,
            'phone' => $this->phone,
            'car' => $this->car,
            'color' => $this->color,
            'status' => $this->status,
            'lat' => (float) $this->gps_lat,
            'lng' => (float) $this->gps_lng,
            'updated_at' => $this->gps_updated_at?->diffForHumans(),
            'background' => $this->isTrackedInBackground(),
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
