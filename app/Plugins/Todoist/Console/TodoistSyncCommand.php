<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Todoist\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{Organization, TodoistConnection, TodoistProjectLink};
use App\Plugins\Todoist\Api\TodoistApiClient;
use App\Plugins\Todoist\Services\{TodoistImportService, TodoistSyncService};
use App\Plugins\Todoist\TodoistConfig;
use Illuminate\Console\Command;

/**
 * Polling-Abgleich als verlässliche Quelle (Feature 055, MVP-115): läuft
 * stündlich im Scheduler (withoutOverlapping) und manuell. Delta über das
 * `sync_token` in `todoist_connections.sync_cursor` — nur Projektzuordnungen
 * mit geänderten Items werden abgeglichen; `--full` (oder ein fehlender
 * Cursor) erzwingt den Vollabgleich, der auch verpasste/unsortierte Webhooks
 * heilt. Bricht ein Lauf ab (z. B. 429 nach ausgeschöpftem Toolkit-Backoff),
 * bleibt der Cursor unverändert — der nächste Lauf setzt dort wieder auf.
 */
class TodoistSyncCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'todoist:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich erzwingen (ignoriert den Sync-Cursor)}';

    protected $description = 'Gleicht Todoist-Aufgaben je Organisation ab (cursor-basiertes Delta, --full erzwingt Vollabgleich).';

    public function handle(TodoistImportService $imports, TodoistSyncService $sync): int {
        if (! TodoistConfig::isConfigured()) {
            $this->warn('Todoist ist nicht konfiguriert (TODOIST_CLIENT_ID/TODOIST_CLIENT_SECRET) — übersprungen.');

            return self::SUCCESS;
        }

        // Bei Abbruch bleibt der Cursor unverändert — Wiederanlauf am selben Stand.
        $this->forEachOrganization(function (Organization $org) use ($imports, $sync): void {
            $connection = TodoistConnection::query()->withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->first();
            if (! $connection instanceof TodoistConnection || ! $connection->isActive()) {
                return;
            }

            $counters = $this->syncOrganization($org, $connection, $imports, $sync);
            $this->info(sprintf(
                'Organisation #%d (%s): created %d, updated %d, unchanged %d, conflicts %d, inbox %d, failed %d',
                $org->id, $org->name,
                $counters['created'], $counters['updated'], $counters['unchanged'],
                $counters['conflicts'], $counters['inbox'], $counters['failed'],
            ));
        });

        return self::SUCCESS;
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function syncOrganization(Organization $org, TodoistConnection $connection, TodoistImportService $imports, TodoistSyncService $sync): array {
        $api = new TodoistApiClient($connection);
        $cursor = (string) ($connection->getAttribute('sync_cursor') ?? '');

        if ((bool) $this->option('full') || $cursor === '') {
            // Token VOR dem Import ziehen: Änderungen während des Laufs werden
            // beim nächsten Delta erneut gesehen (at-least-once statt Lücke).
            $token = $api->getLatestSyncToken();
            $counters = $sync->syncOrganization($org);
            if ($token !== null) {
                $connection->forceFill(['sync_cursor' => $token])->save();
            }

            return $counters;
        }

        $delta = $api->sync($cursor, ['items']);

        if ((bool) ($delta['full_sync'] ?? false)) {
            $counters = $sync->syncOrganization($org);
        } else {
            $counters = TodoistSyncService::emptyCounters();
            $items = collect(is_array($delta['items'] ?? null) ? $delta['items'] : []);
            if ($items->isNotEmpty()) {
                $links = TodoistProjectLink::query()
                    ->where('organization_id', $org->id)
                    ->where('status', TodoistProjectLink::STATUS_ACTIVE)
                    ->get()
                    ->keyBy('todoist_project_id');

                /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $group */
                foreach ($items->groupBy(fn (array $i): string => (string) ($i['project_id'] ?? '')) as $projectId => $group) {
                    $link = $links->get((string) $projectId);
                    if (! $link instanceof TodoistProjectLink) {
                        continue; // Projekt ohne Zuordnung — bewusst ignoriert
                    }
                    $result = $imports->syncItems($link, $group->values());
                    foreach ($counters as $key => $value) {
                        $counters[$key] = $value + $result[$key];
                    }
                }
            }
            $connection->forceFill(['last_sync_at' => now()])->save();
        }

        $token = $delta['sync_token'] ?? null;
        if (is_string($token) && $token !== '') {
            $connection->forceFill(['sync_cursor' => $token])->save();
        }

        return $counters;
    }
}
