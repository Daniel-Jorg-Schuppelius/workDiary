<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphTodoWakeJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Jobs;

use App\Models\{MsgraphTaskConnection, MsgraphTaskListLink, Organization};
use App\Plugins\Msgraph\Services\MsgraphTodoSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Throwable;

/**
 * Gezielter To-Do-Abgleich eines Listen-Links nach Graph-Webhook-Impuls
 * (Feature 102, Folgeausbau — Muster
 * {@see \App\Plugins\Todoist\Jobs\TodoistWebhookSyncJob}): läuft den
 * regulären 3-Wege-Sync inkl. Delta-Checkpoint — der Webhook schreibt nie
 * direkt Aufgaben. Fehler werden geschluckt: das stündliche Polling
 * (msgraph:todo-sync) heilt.
 */
class MsgraphTodoWakeJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $linkId,
    ) {}

    public function handle(MsgraphTodoSyncService $sync): void {
        $org = Organization::query()->find($this->organizationId);
        if (! $org instanceof Organization) {
            return;
        }

        \App\Support\OrganizationContext::run($org, function () use ($sync, $org): void {
            $link = MsgraphTaskListLink::query()
                ->where('organization_id', $org->id)
                ->whereKey($this->linkId)
                ->where('status', MsgraphTaskListLink::STATUS_ACTIVE)
                ->first();
            $connection = MsgraphTaskConnection::query()
                ->where('organization_id', $org->id)
                ->first();
            if (! $link instanceof MsgraphTaskListLink
                || ! $connection instanceof MsgraphTaskConnection
                || ! $connection->isActive()) {
                return;
            }

            try {
                $sync->syncLink($link, $connection);
            } catch (Throwable) {
                // bewusst: Polling heilt — Webhook ist nur Impuls
            }
        });
    }
}
