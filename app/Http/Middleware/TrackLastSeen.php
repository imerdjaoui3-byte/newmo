<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the signed-in user's "last seen" time fresh, so they show as online while they use the app.
 */
class TrackLastSeen
{
    /**
     * Record the visit, at most once a minute so browsing doesn't write on every request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->exists && ! $user->last_seen_at?->gte(now()->subMinute())) {
            User::whereKey($user->id)->toBase()->update(['is_online' => true, 'last_seen_at' => now()]);
        }

        return $next($request);
    }
}
