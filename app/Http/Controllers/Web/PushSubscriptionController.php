<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /** Hands the browser what it needs to call pushManager.subscribe(). */
    public function config(WebPushSender $sender): JsonResponse
    {
        return response()->json([
            'enabled'    => $sender->configured(),
            'public_key' => config('webpush.vapid.public_key'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'         => ['required', 'string', 'max:1000'],
            'keys.p256dh'      => ['required', 'string'],
            'keys.auth'        => ['required', 'string'],
            'content_encoding' => ['nullable', 'string', 'max:20'],
        ]);

        // Keyed on the endpoint so a browser that re-subscribes (key rotation,
        // permission re-grant) updates its row instead of piling up duplicates.
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashFor($data['endpoint'])],
            [
                'user_id'          => $request->user()->id,
                'endpoint'         => $data['endpoint'],
                'public_key'       => $data['keys']['p256dh'],
                'auth_token'       => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aesgcm',
                'user_agent'       => substr((string) $request->userAgent(), 0, 255),
            ],
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->input('endpoint');

        if ($endpoint) {
            PushSubscription::where('user_id', $request->user()->id)
                ->where('endpoint_hash', PushSubscription::hashFor($endpoint))
                ->delete();
        }

        return response()->json(['status' => 'unsubscribed']);
    }
}
