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
use App\Plugins\OrgaMax\Api\OrgaMaxClientFactory;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use Throwable;

/**
 * Stammdaten-Abgleich ohne Webhooks (Feature 077, MVP-313): Kunden,
 * Lieferanten und Artikel je aktivierter Capability als budgetierter Sweep
 * ({@see OrgaMaxPagedSweep}). Rechnungen und Ausgaben liest der Belegspiegel
 * {@see OrgaMaxVoucherPullService} (MVP-1501) im selben geplanten Lauf.
 * Backoff bei Rate-Limit und 5xx kommt aus dem api-toolkit; Fehler eines
 * Bereichs stoppen die anderen nicht.
 */
class OrgaMaxSyncService {
    public function __construct(
        private readonly OrgaMaxClientFactory $clients,
        private readonly OrgaMaxMasterDataImporter $importer,
        private readonly OrgaMaxPagedSweep $sweep,
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
                $counters += $this->sweep->run($connection, $capability, $reader, $pageSize, $budget);
            } catch (ApiException $e) {
                $errors[] = $capability . ': HTTP ' . $e->getCode();
            } catch (Throwable) {
                $errors[] = $capability . ': error';
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
}
