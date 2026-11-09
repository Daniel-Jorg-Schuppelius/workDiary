<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxPagedSweep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use Illuminate\Support\Carbon;

/**
 * Budgetierter Sweep einer orgaMAX-Liste (Feature 077, MVP-313): Die OpenAPI
 * dokumentiert keinen `updated_since`-Cursor. Ein Lauf liest höchstens
 * `$budget` Seiten ab dem Offset-Checkpoint `checkpoints[<resource>_offset]`;
 * eine kurze Seite schließt den Vollabgleich ab (`checkpoints[<resource>]`)
 * und setzt den Offset zurück. Stammdatenabgleich und Belegspiegel teilen sich
 * diese Etappen.
 */
final class OrgaMaxPagedSweep {
    /**
     * @param  callable(int): array{read: int, linked?: int, inboxed?: int, updated?: int}  $reader  Seite ab Offset
     * @return array<string, int> Zähler `<resource>_read|linked|inboxed|updated` (nur > 0)
     */
    public function run(OrgaMaxConnection $connection, string $resource, callable $reader, int $pageSize, int $budget): array {
        $checkpoints = (array) $connection->checkpoints;
        $offset = max(0, (int) ($checkpoints[$resource . '_offset'] ?? 0));
        $read = 0;
        $linked = 0;
        $inboxed = 0;
        $updated = 0;

        for ($page = 0; $page < max(1, $budget); $page++) {
            $result = $reader($offset);
            $read += (int) $result['read'];
            $linked += (int) ($result['linked'] ?? 0);
            $inboxed += (int) ($result['inboxed'] ?? 0);
            $updated += (int) ($result['updated'] ?? 0);

            if ((int) $result['read'] < $pageSize) {
                $offset = 0;
                $checkpoints[$resource] = Carbon::now()->toIso8601String();
                break;
            }
            $offset += $pageSize;
        }

        $checkpoints[$resource . '_offset'] = $offset;
        $connection->checkpoints = $checkpoints;
        $connection->save();

        return array_filter([
            $resource . '_read' => $read,
            $resource . '_linked' => $linked,
            $resource . '_inboxed' => $inboxed,
            $resource . '_updated' => $updated,
        ], static fn (int $value): bool => $value > 0);
    }
}
