<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphMailWakeJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Jobs;

use App\Jobs\Concerns\RetriesTransientFailures;
use App\Models\Mail\EmailConnection;
use App\Models\Platform\Organization;
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Services\Mail\MailboxPoller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Gezielter Postfach-Abruf nach Graph-Webhook-Impuls (Feature 102,
 * Folgeausbau): ruft genau das betroffene Graph-Postfach über denselben
 * {@see MailboxPoller} ab wie `mail:poll` — die Sperre je Postfach verhindert
 * doppelte Verarbeitung. Das 5-Minuten-Polling heilt verpasste Impulse.
 */
class MsgraphMailWakeJob implements ShouldQueue {
    use RetriesTransientFailures;

    protected function pluginErrorId(): ?string {
        return MsgraphPlugin::ID;
    }
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $connectionId,
    ) {}

    public function handle(MailboxPoller $poller): void {
        $org = Organization::query()->find($this->organizationId);
        if (! $org instanceof Organization) {
            return;
        }

        \App\Support\OrganizationContext::run($org, function () use ($poller, $org): void {
            $connection = EmailConnection::query()
                ->where('organization_id', $org->id)
                ->whereKey($this->connectionId)
                ->first();
            if ($connection instanceof EmailConnection && $connection->isActive()) {
                $poller->poll($org, $connection);
            }
        });
    }
}
