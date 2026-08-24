<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebPushDeliveryJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Jobs\Notification;

use App\Jobs\Concerns\RetriesTransientFailures;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * WebPush-Zustellung außerhalb des HTTP-Requests (Vollscan 2026-08-23, A2):
 * Je Subscription ein HTTP-Roundtrip zum Push-Dienst — das lief bisher im
 * Request des auslösenden Nutzers. Der Job bündelt alle Subscriptions des
 * Empfängers in einem flush(); abgelaufene Subscriptions räumt der Service.
 */
class WebPushDeliveryJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RetriesTransientFailures;
    use SerializesModels;

    /**
     * @param  array{title: string, body?: string, url?: string, tag?: string, icon?: string}  $payload
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $payload,
    ) {
        $this->afterCommit();
    }

    public function handle(WebPushService $webPush): void {
        $user = User::query()->withoutGlobalScopes()->find($this->userId);
        if (! $user instanceof User) {
            return;
        }

        $webPush->sendToUser($user, $this->payload);
    }
}
