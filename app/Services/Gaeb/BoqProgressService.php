<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqProgressService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\{BoqItemStatus, BoqProgressSource};
use App\Models\{BoqItem, BoqItemProgress};
use Illuminate\Support\Carbon;

/**
 * Aufmaß-/Fortschrittserfassung je LV-Position (Feature 049, MVP-083). Meldungen
 * sind additiv; sobald eine Position Fortschritt hat und noch nicht
 * abgeschlossen ist, wird sie auf „in Arbeit" gehoben.
 */
class BoqProgressService {
    /**
     * @param array{source?: BoqProgressSource, diary_entry_id?: int|null, material_usage_id?: int|null, note?: string|null, created_by?: int|null, captured_at?: Carbon|null} $options
     */
    public function record(BoqItem $item, float|string $quantity, array $options = []): BoqItemProgress {
        $progress = BoqItemProgress::query()->create([
            'organization_id' => $item->organization_id,
            'boq_item_id' => $item->id,
            'quantity' => (string) $quantity,
            'source' => ($options['source'] ?? BoqProgressSource::Manual)->value,
            'diary_entry_id' => $options['diary_entry_id'] ?? null,
            'material_usage_id' => $options['material_usage_id'] ?? null,
            'note' => $options['note'] ?? null,
            'captured_at' => $options['captured_at'] ?? $item->freshTimestamp(),
            'created_by' => $options['created_by'] ?? null,
        ]);

        $this->refreshItemStatus($item);

        return $progress;
    }

    /** Hebt eine begonnene, nicht abgeschlossene Position auf „in Arbeit". */
    private function refreshItemStatus(BoqItem $item): void {
        if (in_array($item->status, [BoqItemStatus::Completed, BoqItemStatus::Cancelled, BoqItemStatus::Replaced], true)) {
            return;
        }

        if ($item->executedQuantity() > 0.0 && $item->status !== BoqItemStatus::InProgress) {
            $item->forceFill(['status' => BoqItemStatus::InProgress])->save();
        }
    }
}
