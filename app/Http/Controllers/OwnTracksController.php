<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Receives positions from the OwnTracks app running in the background on a driver's phone (HTTP mode).
 *
 * @see https://owntracks.org/booklet/tech/http/
 */
class OwnTracksController extends Controller
{
    /**
     * Record a driver's location. OwnTracks signs in with HTTP Basic auth (account email + tracking secret) and
     * expects a JSON array back; any other answer makes it keep the point and retry, so bad points are dropped with 200.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $driver = Driver::findByTrackingCredentials($request->getUser(), $request->getPassword());

        if (! $driver) {
            return response()->json(['message' => 'Invalid tracking credentials.'], 401)
                ->header('WWW-Authenticate', 'Basic realm="OwnTracks"');
        }

        if ($request->input('_type') !== 'location') {
            return response()->json([]);
        }

        $validator = Validator::make($request->all(), [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'tst' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->passes()) {
            $recordedAt = Carbon::createFromTimestamp($request->integer('tst'));

            $driver->recordLocation((float) $request->input('lat'), (float) $request->input('lon'), $recordedAt->min(now()));
        }

        return response()->json([]);
    }
}
