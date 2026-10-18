<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetUpDriverTrackingRequest;
use Illuminate\Http\RedirectResponse;

class DriverTrackingSetupController extends Controller
{
    /**
     * Issue a new tracking secret and hand the driver a link that configures OwnTracks on their phone in one tap.
     * The secret is only shown this once, so setting up again disconnects the previous phone.
     */
    public function __invoke(SetUpDriverTrackingRequest $request): RedirectResponse
    {
        $user = $request->user();
        $token = $user->driver->issueTrackingToken();

        $configuration = [
            '_type' => 'configuration',
            'mode' => 3,
            'url' => route('owntracks.store'),
            'auth' => true,
            'username' => $user->email,
            'password' => $token,
            'deviceId' => 'phone',
            'tid' => $user->initials(),
            'monitoring' => 2,
            'locatorInterval' => 60,
            'locatorDisplacement' => 50,
        ];

        return redirect()->route('dashboard')->with('trackingSetup', [
            'link' => 'owntracks:///config?inline='.rawurlencode(base64_encode(json_encode($configuration, JSON_UNESCAPED_SLASHES))),
            'url' => $configuration['url'],
            'username' => $configuration['username'],
            'password' => $token,
        ]);
    }
}
