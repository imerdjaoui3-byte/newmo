<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Socialite\Two\User as GoogleUser;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /**
     * How long after their last visit a logged-in user still counts as online.
     */
    public const ONLINE_MINUTES = 5;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'password',
        'rank',
        'is_online',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rank' => 'integer',
            'is_online' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Get the driver profile associated with the user.
     */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    /**
     * Get the login/logout activity history for the user.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class);
    }

    /**
     * Get the Gmail inbox the user granted read access to.
     */
    public function mailbox(): HasOne
    {
        return $this->hasOne(Mailbox::class);
    }

    /**
     * Save the Gmail tokens Google returned, keeping the stored refresh token when Google doesn't send a new one.
     */
    public function storeGmailAccess(GoogleUser $googleUser): Mailbox
    {
        $mailbox = $this->mailbox()->firstOrNew();

        $mailbox->fill([
            'email' => $googleUser->getEmail(),
            'access_token' => $googleUser->token,
            'refresh_token' => $googleUser->refreshToken ?? $mailbox->refresh_token,
            'expires_at' => now()->addSeconds($googleUser->expiresIn),
        ])->save();

        return $mailbox;
    }

    /**
     * Get the orders dispatched to this user as a driver.
     */
    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'driver_id');
    }

    /**
     * Get the stock loaded onto this user as a driver.
     */
    public function driverLoads(): HasMany
    {
        return $this->hasMany(DriverLoad::class, 'driver_id');
    }

    /**
     * Get the end-of-day bills issued to this user as a driver.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'driver_id');
    }

    /**
     * Get how much money this driver collected on orders completed on the given day.
     */
    public function revenueOn(CarbonInterface $date): float
    {
        return (float) OrderItem::query()
            ->whereHas('order', fn (Builder $query) => $query->where('driver_id', $this->id)->whereDate('completed_at', $date))
            ->sum(DB::raw('quantity * unit_price'));
    }

    /**
     * Get how many of each product this driver sold on orders completed on the given day.
     *
     * @return Collection<int, int>
     */
    public function soldQuantitiesOn(CarbonInterface $date): Collection
    {
        return OrderItem::query()
            ->whereHas('order', fn (Builder $query) => $query->where('driver_id', $this->id)->whereDate('completed_at', $date))
            ->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity) as sold')
            ->pluck('sold', 'product_id')
            ->map(fn (mixed $sold): int => (int) $sold);
    }

    /**
     * Scope a query to the staff who run dispatch: developers, super admins and dispatchers.
     */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('rank', [0, 1, 2]);
    }

    /**
     * Scope a query to only include users with an assigned rank (rank 0 is hidden everywhere).
     */
    public function scopeRanked(Builder $query): Builder
    {
        return $query->where('rank', '>', 0);
    }

    /**
     * Get the user's initials, for use in an avatar.
     */
    public function initials(): string
    {
        $initials = collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->map(fn (string $part) => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');

        return mb_strtoupper($initials) ?: '?';
    }

    /**
     * Get a consistent avatar background color for the user.
     */
    public function avatarColor(): string
    {
        $palette = ['--accent-color', '--success-color', '--warning-color', '--danger-color', '--info-color'];

        $index = crc32($this->email) % count($palette);

        return 'var('.$palette[$index].')';
    }

    /**
     * Get the role label for every rank, keyed by rank.
     *
     * @return array<int, string>
     */
    public static function roleLabels(): array
    {
        return [
            0 => 'Developer',
            1 => 'Super Admin',
            2 => 'Dispatch',
            3 => 'Driver',
        ];
    }

    /**
     * Get the role label matching the user's rank.
     */
    public function roleLabel(): string
    {
        if ($this->isPending()) {
            return 'Pending';
        }

        return self::roleLabels()[$this->rank] ?? 'Unknown';
    }

    /**
     * Determine if the user signed up with Google and is still waiting for an admin to assign a rank.
     */
    public function isPending(): bool
    {
        return $this->rank === null;
    }

    /**
     * Determine whether the user is using the app right now. Being logged in is not enough: closing the browser
     * never logs anyone out. Drivers follow the live tracking rules instead.
     */
    public function isOnline(): bool
    {
        if ($this->isDriver() && $this->driver) {
            return $this->driver->setRelation('user', $this)->isOnline();
        }

        return $this->is_online && (bool) $this->last_seen_at?->gte(now()->subMinutes(self::ONLINE_MINUTES));
    }

    /**
     * Determine if the user is a Driver.
     */
    public function isDriver(): bool
    {
        return $this->rank === 3;
    }

    /**
     * Get the badge color variant for the user's rank.
     */
    public function roleBadgeVariant(): string
    {
        return match ($this->rank) {
            null => 'muted',
            1 => 'danger',
            2 => 'warning',
            3 => 'info',
            default => 'secondary',
        };
    }
}
