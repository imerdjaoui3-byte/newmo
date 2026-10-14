<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\Response;

class DeviceTokenController extends Controller
{
    /**
     * Save the browser's push notification token for the signed-in user, moving it over if another user registered it before.
     */
    public function __invoke(StoreDeviceTokenRequest $request): Response
    {
        DeviceToken::updateOrCreate(
            ['token' => $request->validated('token')],
            ['user_id' => $request->user()->id],
        );

        return response()->noContent();
    }
}
