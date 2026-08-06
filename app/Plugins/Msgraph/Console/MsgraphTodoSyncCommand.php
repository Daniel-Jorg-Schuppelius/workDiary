<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphTodoSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Console;

use App\Models\{MsgraphTaskConnection, Organization};
use App\Plugins\Msgraph\Services\MsgraphTodoSyncService;
use Illuminate\Console\Command;

/**
 * Microsoft-To-Do-Abgleich aller Organisationen mit aktiver Verbindung
 * (Feature 102, Schnitt E — Todoist-Muster `todoist:sync`): idempotent,
 * Konflikte in die Integrations-Inbox, nie Last-write-wins.
 */
class MsgraphTodoSyncCommand extends Command {
    protected $signature = 'msgraph:todo-sync
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Gleicht Microsoft-To-Do-Listen mit den zugeordneten WorkDiary-Projekten ab (idempotent).';

    public function handle(MsgraphTodoSyncService $sync): int {
        $orgOption = $this->option('organization');

        $organizationIds = MsgraphTaskConnection::query()
            ->withoutGlobalScopes()
            ->when(is_numeric($orgOption), fn ($q) => $q->where('organization_id', (int) $orgOption))
            ->pluck('organization_id');

        $totals = MsgraphTodoSyncService::emptyCounters();
        foreach (Organization::query()->whereIn('id', $organizationIds)->get() as $organization) {
            $result = $sync->syncOrganization($organization);
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $result[$key];
            }
        }

        $this->info(sprintf(
            'To-Do-Sync: %d neu, %d aktualisiert, %d unverändert, %d Konflikte, %d Inbox, %d Fehler',
            $totals['created'],
            $totals['updated'],
            $totals['unchanged'],
            $totals['conflicts'],
            $totals['inbox'],
            $totals['failed'],
        ));

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
