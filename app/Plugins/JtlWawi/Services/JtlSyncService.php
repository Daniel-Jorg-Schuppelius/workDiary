<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlSyncService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Models\JtlConnection;
use Throwable;

/**
 * Orchestriert einen Sync-Lauf (Feature 078, MVP-322): Lager-Projektion →
 * Artikel-Projektion → Bestands-Delta-Polling. Läuft je Organisation,
 * respektiert die Laufbudgets der Teil-Services und protokolliert Zähler +
 * gekürzte Fehlerklasse an der Verbindung (nie Payloads/Secrets). Der
 * „Jetzt synchronisieren“-Button nutzt denselben Pfad wie der Scheduler —
 * kein paralleler Vollabgleich.
 */
class JtlSyncService {
    public function __construct(
        private readonly JtlWarehouseImporter $warehouses,
        private readonly JtlArticleImporter $articles,
        private readonly JtlStockChangePoller $stocks,
    ) {}

    /** @return array<string, mixed> Zähler je Teil-Lauf (für Sync-Protokoll/UI). */
    public function run(JtlConnection $connection): array {
        $counters = [];

        try {
            $counters['warehouses'] = $this->warehouses->import($connection);
            $counters['articles'] = $this->articles->import($connection);
            $counters['stocks'] = $this->stocks->poll($connection);

            $connection->forceFill([
                'last_sync_at' => now(),
                'last_sync_counters' => $counters,
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            $connection->forceFill([
                'last_error' => mb_substr(class_basename($e) . ': ' . $e->getMessage(), 0, 191),
            ])->save();

            throw $e;
        }

        return $counters;
    }
}
