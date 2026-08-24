<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphCalendarWakeJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Jobs;

use App\Jobs\Concerns\RetriesTransientFailures;
use App\Models\{MsgraphConnection, Organization};
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Msgraph\Services\MsgraphCalendarImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Throwable;

/**
 * Gezielter Kalender-Rückimport nach Graph-Webhook-Impuls (Feature 102,
 * Folgeausbau — Muster {@see \App\Plugins\Todoist\Jobs\TodoistWebhookSyncJob}):
 * bindet die Organisation explizit und läuft den regulären Delta-Import der
 * Verbindung — der Webhook schreibt nie direkt Termine. Fehler werden
 * geschluckt: das stündliche Polling (msgraph:calendar-import) heilt.
 */
class MsgraphCalendarWakeJob implements ShouldQueue {
    use RetriesTransientFailures;

    protected function pluginErrorId(): ?string {
        return MsgraphPlugin::ID;
    }
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $organizationId) {}

    public function handle(MsgraphCalendarImportService $imports): void {
        $org = Organization::query()->find($this->organizationId);
        if (! $org instanceof Organization) {
            return;
        }

        \App\Support\OrganizationContext::run($org, function () use ($imports, $org): void {
            $connection = MsgraphConnection::query()
                ->where('organization_id', $org->id)
                ->first();
            if (! $connection instanceof MsgraphConnection) {
                return;
            }

            try {
                $imports->run($connection); // prüft two_way/isActive selbst
            } catch (Throwable) {
                // bewusst: Polling heilt — Webhook ist nur Impuls
            }
        });
    }
}
