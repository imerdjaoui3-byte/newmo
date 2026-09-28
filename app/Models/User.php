<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
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
     * Get the push-notification device tokens registered for the user.
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
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
            ->map(fn(string $part) => mb_substr($part, 0, 1))
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

        return 'var(' . $palette[$index] . ')';
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
        return self::roleLabels()[$this->rank] ?? 'Unknown';
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
            1 => 'danger',
            2 => 'warning',
            3 => 'info',
            default => 'secondary',
        };
    }
}
