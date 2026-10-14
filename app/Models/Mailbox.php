<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * A user's Gmail inbox the app has read-only access to.
 */
class Mailbox extends Model
{
    /**
     * Google scope granting read-only access to Gmail.
     */
    public const GMAIL_READONLY_SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the user who owns the inbox.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a valid access token, refreshing it with Google when it is about to expire.
     */
    public function freshAccessToken(): string
    {
        if ($this->refresh_token && $this->expires_at?->isBefore(now()->addMinute())) {
            $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $this->refresh_token,
                'grant_type' => 'refresh_token',
            ])->throw()->json();

            $this->update([
                'access_token' => $token['access_token'],
                'expires_at' => now()->addSeconds($token['expires_in']),
            ]);
        }

        return $this->access_token;
    }

    /**
     * Get when the latest email containing the given text (such as an e-Transfer reference) arrived, or null if none does.
     */
    public function findMessageDateContaining(string $text): ?Carbon
    {
        $gmail = Http::withToken($this->freshAccessToken())
            ->baseUrl('https://gmail.googleapis.com/gmail/v1/users/me')
            ->throw();

        $phrase = str_replace('"', '', $text);
        $messageId = $gmail->get('messages', ['q' => "\"{$phrase}\"", 'maxResults' => 1])->json('messages.0.id');

        if (! $messageId) {
            return null;
        }

        $receivedAtMilliseconds = (int) $gmail->get("messages/{$messageId}", ['format' => 'minimal'])->json('internalDate');

        return Carbon::createFromTimestampMs($receivedAtMilliseconds);
    }

    /**
     * Get the most recent messages in the inbox.
     *
     * @return list<array{id: string, from: ?string, subject: ?string, date: ?Carbon, snippet: string}>
     */
    public function recentMessages(int $limit = 10): array
    {
        $gmail = Http::withToken($this->freshAccessToken())
            ->baseUrl('https://gmail.googleapis.com/gmail/v1/users/me')
            ->throw();

        $messageIds = collect($gmail->get('messages', ['maxResults' => $limit])->json('messages', []))->pluck('id');

        return $messageIds->map(function (string $id) use ($gmail) {
            $message = $gmail->get("messages/{$id}", [
                'format' => 'metadata',
                'metadataHeaders' => ['From', 'Subject', 'Date'],
            ])->json();

            $headers = collect($message['payload']['headers'] ?? [])->pluck('value', 'name');

            return [
                'id' => $id,
                'from' => $headers->get('From'),
                'subject' => $headers->get('Subject'),
                'date' => $headers->has('Date') ? rescue(fn () => Carbon::parse($headers->get('Date')), report: false) : null,
                'snippet' => html_entity_decode($message['snippet'] ?? '', ENT_QUOTES),
            ];
        })->all();
    }
}
