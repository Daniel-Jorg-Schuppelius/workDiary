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
use App\Models\{EmailConnection, Organization};
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Services\Mail\{MailIntakeService, MailboxGateway};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Gezielter Postfach-Abruf nach Graph-Webhook-Impuls (Feature 102,
 * Folgeausbau): ruft genau das betroffene Graph-Postfach ab — gleiche
 * Verarbeitung wie {@see \App\Console\Commands\MailPollCommand} (Intake in
 * die Integrations-Inbox, markProcessed, Health-Zähler). Fehler zählen auf
 * den Verbindungs-Health; das 5-Minuten-Polling (mail:poll) heilt verpasste
 * Impulse.
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

    public function handle(MailboxGateway $gateway, MailIntakeService $intake): void {
        $org = Organization::query()->find($this->organizationId);
        if (! $org instanceof Organization) {
            return;
        }

        \App\Support\OrganizationContext::run($org, function () use ($gateway, $intake, $org): void {
            $connection = EmailConnection::query()
                ->where('organization_id', $org->id)
                ->whereKey($this->connectionId)
                ->first();
            if (! $connection instanceof EmailConnection || ! $connection->isActive()) {
                return;
            }

            try {
                foreach ($gateway->fetch($connection) as $message) {
                    $intake->intake($org, $connection, $message);
                    $gateway->markProcessed($connection, $message);
                }
                $connection->forceFill(['last_polled_at' => Carbon::now()])->save();
                $connection->recordConnectionSuccess();
            } catch (Throwable $e) {
                $connection->recordConnectionFailure($e->getMessage());
            }
        });
    }
}
