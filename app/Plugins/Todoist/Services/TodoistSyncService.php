<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistSyncService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Todoist\Services;

use App\Models\{Organization, TodoistConnection, TodoistProjectLink};
use Throwable;

/**
 * Einstieg des Aufgaben-Abgleichs (Feature 055): iteriert die aktiven
 * Projektzuordnungen der Organisation und summiert die Lauf-Zähler. Ein
 * fehlerhafter Projektlauf bricht die übrigen nicht ab.
 *
 * @phpstan-type SyncCounters array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}
 */
class TodoistSyncService {
    public function __construct(private readonly TodoistImportService $imports) {}

    /** @return SyncCounters */
    public function syncOrganization(Organization $organization): array {
        $counters = self::emptyCounters();

        $connection = TodoistConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof TodoistConnection || ! $connection->isActive()) {
            return $counters;
        }

        $links = TodoistProjectLink::query()
            ->where('organization_id', $organization->id)
            ->where('status', TodoistProjectLink::STATUS_ACTIVE)
            ->get();

        foreach ($links as $link) {
            try {
                $result = $this->imports->syncLink($link, $connection);
                foreach ($counters as $key => $value) {
                    $counters[$key] = $value + $result[$key];
                }
            } catch (Throwable) {
                $counters['failed']++;
            }
        }

        $connection->forceFill(['last_sync_at' => now()])->save();

        return $counters;
    }

    /** @return SyncCounters */
    public static function emptyCounters(): array {
        return ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflicts' => 0, 'inbox' => 0, 'failed' => 0];
    }
}
