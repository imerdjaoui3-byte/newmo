<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyPushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    /**
     * Save the browser's Web Push subscription for the signed-in user, taking it over if another user registered it before.
     */
    public function store(StorePushSubscriptionRequest $request): Response
    {
        $request->user()->updatePushSubscription(
            $request->validated('endpoint'),
            $request->validated('keys.p256dh'),
            $request->validated('keys.auth'),
            $request->validated('contentEncoding', 'aes128gcm'),
        );

        return response()->noContent();
    }

    /**
     * Forget one of the signed-in user's browser subscriptions.
     */
    public function destroy(DestroyPushSubscriptionRequest $request): Response
    {
        $request->user()->deletePushSubscription($request->validated('endpoint'));

        return response()->noContent();
    }
}
