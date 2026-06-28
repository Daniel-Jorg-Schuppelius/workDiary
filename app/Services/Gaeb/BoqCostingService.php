<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqCostingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\BoqItemType;
use App\Models\BillOfQuantity;

/**
 * Nachkalkulation eines LV (Feature 049, MVP-083): Soll-Wert aus Sollmengen ×
 * Einheitspreis, Ist-Wert aus aufgemessenen Mengen × Einheitspreis, plus
 * Fortschrittsgrad. Ersetzt keine Fakturierung — liefert nur die Auswertung.
 */
class BoqCostingService {
    /**
     * @return array{planned: float, executed: float, remaining: float, progress: float, currency: string}
     */
    public function summarize(BillOfQuantity $boq): array {
        $boq->loadMissing(['items.progress']);

        $planned = 0.0;
        $executed = 0.0;

        foreach ($boq->items as $item) {
            if (!$item->type->isBillable() || $item->unit_price === null) {
                continue;
            }

            $unitPrice = (float) $item->unit_price;
            $planned += (float) $item->quantity * $unitPrice;
            $executed += $item->executedQuantity() * $unitPrice;
        }

        $progress = $planned > 0.0 ? round($executed / $planned, 4) : 0.0;

        return [
            'planned' => round($planned, 2),
            'executed' => round($executed, 2),
            'remaining' => round(max(0.0, $planned - $executed), 2),
            'progress' => $progress,
            'currency' => $boq->currency,
        ];
    }

    /** Positionsart-Filter als kleine Hilfe für Aufrufer/Views. */
    public function isBillable(BoqItemType $type): bool {
        return $type->isBillable();
    }
}
