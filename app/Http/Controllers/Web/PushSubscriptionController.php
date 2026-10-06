<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\SafeUrlFetcher;
use App\Services\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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

    public function store(Request $request, SafeUrlFetcher $guard): JsonResponse
    {
        $data = $request->validate([
            'endpoint'         => ['required', 'string', 'max:1000', 'url:https'],
            'keys.p256dh'      => ['required', 'string', 'max:255'],
            'keys.auth'        => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
        ]);

        // The server POSTs to this address whenever someone in the family
        // pays, so it is an outbound request the client chooses. Browsers
        // only ever hand out public push-service URLs; anything pointing into
        // the server's own network is refused.
        try {
            $guard->assertSafe($data['endpoint']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => __('messages.push_endpoint_invalid')], 422);
        }

        // Keyed on the endpoint so a browser that re-subscribes (key rotation,
        // permission re-grant) updates its row instead of piling up duplicates.
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashFor($data['endpoint'])],
            [
                'user_id'          => $request->user()->id,
                'endpoint'         => $data['endpoint'],
                'public_key'       => $data['keys']['p256dh'],
                'auth_token'       => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
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
