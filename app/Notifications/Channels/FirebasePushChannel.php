<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Sends web push notifications to a user's registered browsers through Firebase Cloud Messaging (HTTP v1 API).
 *
 * Failures are reported but never thrown, so a Firebase outage can't break the action that triggered the notification.
 */
class FirebasePushChannel
{
    /**
     * OAuth scope that allows sending FCM messages.
     */
    private const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * Send the notification to every browser the notifiable registered, forgetting tokens Firebase no longer knows.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $tokens = $notifiable->deviceTokens()->get();

        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $credentials = $this->credentials();
            $accessToken = $this->accessToken($credentials);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        /** @var array{title: string, body: string, link: string} $message */
        $message = $notification->toFirebasePush($notifiable);

        foreach ($tokens as $deviceToken) {
            rescue(fn () => $this->sendToDevice($deviceToken, $message, $credentials['project_id'], $accessToken));
        }
    }

    /**
     * Push one message to one browser, deleting its token when Firebase says it is no longer registered.
     *
     * @param  array{title: string, body: string, link: string}  $message
     */
    private function sendToDevice(DeviceToken $deviceToken, array $message, string $projectId, string $accessToken): void
    {
        $response = Http::withToken($accessToken)->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
            'message' => [
                'token' => $deviceToken->token,
                'notification' => [
                    'title' => $message['title'],
                    'body' => $message['body'],
                ],
                'webpush' => [
                    'notification' => ['icon' => asset('favicon.ico')],
                    'fcm_options' => ['link' => $message['link']],
                ],
            ],
        ]);

        if ($response->successful()) {
            return;
        }

        if ($response->status() === 404 || $response->json('error.details.0.errorCode') === 'UNREGISTERED') {
            $deviceToken->delete();

            return;
        }

        report(new RequestException($response));
    }

    /**
     * Get a short-lived OAuth access token for the service account, cached until shortly before it expires.
     *
     * @param  array{client_email: string, private_key: string, token_uri: string}  $credentials
     */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('firebase-push.access-token', now()->addMinutes(50), function () use ($credentials): string {
            return Http::asForm()->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->signedAssertion($credentials),
            ])->throw()->json('access_token');
        });
    }

    /**
     * Build the RS256-signed JWT that exchanges the service account key for an access token.
     *
     * @param  array{client_email: string, private_key: string, token_uri: string}  $credentials
     */
    private function signedAssertion(array $credentials): string
    {
        $encode = fn (array $data): string => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
            'iss' => $credentials['client_email'],
            'scope' => self::MESSAGING_SCOPE,
            'aud' => $credentials['token_uri'],
            'iat' => now()->timestamp,
            'exp' => now()->addHour()->timestamp,
        ]);

        if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the Firebase service account assertion.');
        }

        return $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /**
     * Read the Firebase service account key file.
     *
     * @return array{project_id: string, client_email: string, private_key: string, token_uri: string}
     */
    private function credentials(): array
    {
        $path = config('services.firebase.credentials');

        if (! $path) {
            throw new RuntimeException('FIREBASE_CREDENTIALS is not set, so push notifications cannot be sent.');
        }

        $path = str_starts_with($path, '/') ? $path : base_path($path);

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
