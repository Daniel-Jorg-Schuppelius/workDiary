<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxSyncService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use APIToolkit\Exceptions\ApiException;
use App\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\Api\OrgaMaxClientFactory;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Polling ohne Webhooks (Feature 077, MVP-313): Die OpenAPI dokumentiert
 * keinen `updated_since`-Cursor — der Abgleich läuft als paginierter,
 * budgetierter Sweep mit Offset-Checkpoint je Ressource. Ein Lauf liest
 * höchstens `sync_page_budget` Seiten je Ressource und setzt den Checkpoint
 * so, dass der nächste Lauf nahtlos fortsetzt; ein vollständiger Durchlauf
 * setzt den Offset zurück (Vollabgleich in Etappen). Backoff bei Rate-Limit
 * und 5xx kommt aus dem api-toolkit; Fehler eines Bereichs stoppen die
 * anderen nicht.
 */
class OrgaMaxSyncService {
    public function __construct(
        private readonly OrgaMaxClientFactory $clients,
        private readonly OrgaMaxMasterDataImporter $importer,
        private readonly OrgaMaxInvoiceProjector $projector,
    ) {}

    /** @return array<string, int> Zähler des Laufs (für Sync-Protokoll). */
    public function run(OrgaMaxConnection $connection): array {
        $client = $this->clients->for($connection);
        $pageSize = max(1, (int) config('plugins.orgamax.page_size', 100));
        $budget = max(1, (int) config('plugins.orgamax.sync_page_budget', 10));
        $counters = [];
        $errors = [];

        $resources = [
            'customers' => fn(int $offset) => $this->importer->importCustomers($connection, $client, $offset, $pageSize),
            'suppliers' => fn(int $offset) => $this->importer->importSuppliers($connection, $client, $offset, $pageSize),
            'articles' => fn(int $offset) => $this->importer->importArticles($connection, $client, $offset, $pageSize),
        ];

        foreach ($resources as $capability => $reader) {
            if (! $connection->capabilityEnabled($capability)) {
                continue;
            }
            try {
                $counters += $this->sweep($connection, $capability, $reader, $pageSize, $budget);
            } catch (ApiException $e) {
                $errors[] = $capability . ': HTTP ' . $e->getCode();
            } catch (Throwable) {
                $errors[] = $capability . ': error';
            }
        }

        if ($connection->capabilityEnabled('billing') || $connection->capabilityEnabled('payments')) {
            try {
                $counters += $this->sweep(
                    $connection,
                    'invoices',
                    fn(int $offset) => $this->projector->project($connection, $client, $offset, $pageSize),
                    $pageSize,
                    $budget,
                );
            } catch (ApiException $e) {
                $errors[] = 'invoices: HTTP ' . $e->getCode();
            } catch (Throwable) {
                $errors[] = 'invoices: error';
            }
        }

        $connection->forceFill([
            'last_sync_at' => now(),
            'last_sync_counters' => $counters,
            // Nur die Fehlerklasse — nie Payloads oder Secrets (MVP-315).
            'last_error' => $errors === [] ? null : mb_substr(implode('; ', $errors), 0, 200),
        ])->save();

        return $counters;
    }

    /**
     * Budgetierter Sweep einer Ressource: Offset-Checkpoint in
     * `checkpoints[<resource>_offset]`, Abschluss-Zeitstempel in
     * `checkpoints[<resource>]`.
     *
     * @param callable(int): array{read: int, linked?: int, inboxed?: int, updated?: int} $reader
     * @return array<string, int>
     */
    private function sweep(OrgaMaxConnection $connection, string $resource, callable $reader, int $pageSize, int $budget): array {
        $checkpoints = (array) $connection->checkpoints;
        $offset = max(0, (int) ($checkpoints[$resource . '_offset'] ?? 0));
        $read = 0;
        $linked = 0;
        $inboxed = 0;
        $updated = 0;

        for ($page = 0; $page < $budget; $page++) {
            $result = $reader($offset);
            $read += (int) $result['read'];
            $linked += (int) ($result['linked'] ?? 0);
            $inboxed += (int) ($result['inboxed'] ?? 0);
            $updated += (int) ($result['updated'] ?? 0);

            if ((int) $result['read'] < $pageSize) {
                // Ende erreicht → Vollabgleich abgeschlossen, Offset zurück.
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
        ], fn(int $v) => $v > 0);
    }
}
