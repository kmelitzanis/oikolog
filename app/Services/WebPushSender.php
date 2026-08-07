<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Delivers Web Push messages to a user's registered browsers.
 *
 * Push is best-effort by design: a dead subscription must never break the
 * request that triggered it, so every failure is logged and swallowed, and
 * subscriptions the push service rejects as gone are pruned.
 */
class WebPushSender
{
    public function configured(): bool
    {
        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    /**
     * @param  iterable<User>  $users
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $payload
     */
    public function sendToUsers(iterable $users, array $payload): void
    {
        $ids = Collection::make($users)
            ->filter(fn (User $u) => (bool) $u->notifications_enabled)
            ->pluck('id');

        if ($ids->isEmpty() || ! $this->configured()) {
            return;
        }

        $subscriptions = PushSubscription::whereIn('user_id', $ids)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush(
                ['VAPID' => [
                    'subject'    => config('webpush.vapid.subject'),
                    'publicKey'  => config('webpush.vapid.public_key'),
                    'privateKey' => config('webpush.vapid.private_key'),
                ]],
                ['TTL' => config('webpush.ttl')],
            );
        } catch (\Throwable $e) {
            Log::warning('Web push disabled: bad VAPID configuration. ' . $e->getMessage());
            return;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $sub) {
            try {
                $webPush->queueNotification(Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->public_key,
                    'authToken'       => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding,
                ]), $body);
            } catch (\Throwable $e) {
                Log::warning("Web push: could not queue subscription {$sub->id}. " . $e->getMessage());
            }
        }

        $byEndpoint = $subscriptions->keyBy('endpoint');

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            $sub = $byEndpoint->get($endpoint);

            if ($report->isSuccess()) {
                $sub?->forceFill(['last_used_at' => now()])->saveQuietly();
                continue;
            }

            // 404/410 mean the browser dropped the subscription for good.
            if ($report->isSubscriptionExpired()) {
                $sub?->delete();
                continue;
            }

            Log::warning('Web push failed for ' . $endpoint . ': ' . $report->getReason());
        }
    }
}
