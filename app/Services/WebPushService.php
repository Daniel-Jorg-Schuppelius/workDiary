<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebPushService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService {
    protected ?WebPush $webPush = null;

    /**
     * Send a push notification to all subscriptions of a user.
     *
     * @param  array{title: string, body?: string, url?: string, tag?: string, icon?: string}  $payload
     */
    public function sendToUser(User $user, array $payload): int {
        $subscriptions = $user->relationLoaded('pushSubscriptions')
            ? $user->pushSubscriptions
            : $user->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $webPush = $this->webPush();
        if (! $webPush) {
            return 0;
        }

        $body = json_encode($payload) ?: null;
        $sent = 0;

        foreach ($subscriptions as $sub) {
            /** @var PushSubscription $sub */
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'keys' => [
                    'p256dh' => $sub->p256dh,
                    'auth' => $sub->auth,
                ],
                'contentEncoding' => $sub->content_encoding ?: 'aesgcm',
            ]);
            $webPush->queueNotification($subscription, $body);
            $sent++;
        }

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            if (! $report->isSuccess()) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $endpoint)->delete();
                } else {
                    Log::warning('WebPush failed', ['endpoint' => $endpoint, 'reason' => $report->getReason()]);
                }

                continue;
            }
            PushSubscription::where('endpoint', $endpoint)->update(['last_used_at' => now()]);
        }

        return $sent;
    }

    /**
     * @param  iterable<User>  $users
     * @param  array{title: string, body?: string, url?: string, tag?: string, icon?: string}  $payload
     */
    public function sendToUsers(iterable $users, array $payload): int {
        $sum = 0;
        foreach ($users as $user) {
            $sum += $this->sendToUser($user, $payload);
        }

        return $sum;
    }

    protected function webPush(): ?WebPush {
        if ($this->webPush !== null) {
            return $this->webPush;
        }

        $public = config('webpush.public_key');
        $private = config('webpush.private_key');
        if (! $public || ! $private) {
            return null;
        }

        return $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => $public,
                'privateKey' => $private,
            ],
        ], [
            'TTL' => config('webpush.ttl'),
        ]);
    }
}
