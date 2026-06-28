<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqWorkflowService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\{BoqItemStatus, BoqItemType};
use App\Models\{BillOfQuantity, BoqItem};
use Illuminate\Support\Collection;

/**
 * LV-Workflow (Feature 049, MVP-084): Statusübergänge für LV-Kopf und
 * Positionen (Ausschreibung → Angebot → Auftrag → Ausführung → Abschluss),
 * Nachträge als eigene Vorgänge sowie die Restleistungssicht. Übergänge sind
 * gerichtet; ungültige Sprünge werfen {@see BoqWorkflowException}.
 */
class BoqWorkflowService {
    /** Erlaubte Statusübergänge (von → nach). */
    private const TRANSITIONS = [
        'draft' => ['quoted', 'ordered', 'cancelled'],
        'imported' => ['quoted', 'ordered', 'cancelled'],
        'quoted' => ['ordered', 'cancelled'],
        'ordered' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => ['replaced'],
        'replaced' => [],
        'cancelled' => [],
    ];

    public function canTransition(BoqItemStatus $from, BoqItemStatus $to): bool {
        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }

    public function transitionBill(BillOfQuantity $boq, BoqItemStatus $to): BillOfQuantity {
        $this->guard($boq->status, $to);
        $boq->forceFill(['status' => $to])->save();

        return $boq;
    }

    public function transitionItem(BoqItem $item, BoqItemStatus $to): BoqItem {
        $this->guard($item->status, $to);
        $item->forceFill(['status' => $to])->save();

        return $item;
    }

    /**
     * Legt einen Nachtrag als eigene LV-Position an (eigenes Kennzeichen,
     * Status Entwurf), optional unter einem bestehenden Abschnitt.
     *
     * @param array{reference_no: string, short_text?: string|null, long_text?: string|null, quantity?: string|null, unit?: string|null, unit_price?: string|null, type?: BoqItemType, boq_section_id?: int|null, created_by?: int|null} $data
     */
    public function createAddendum(BillOfQuantity $boq, array $data): BoqItem {
        $type = $data['type'] ?? BoqItemType::Standard;
        $unitPrice = $data['unit_price'] ?? null;
        $quantity = $data['quantity'] ?? null;
        $total = ($unitPrice !== null && $quantity !== null)
            ? (string) ((float) $unitPrice * (float) $quantity)
            : null;

        return BoqItem::query()->create([
            'organization_id' => $boq->organization_id,
            'bill_of_quantity_id' => $boq->id,
            'boq_section_id' => $data['boq_section_id'] ?? null,
            'reference_no' => $data['reference_no'],
            'type' => $type,
            'status' => BoqItemStatus::Draft,
            'short_text' => $data['short_text'] ?? null,
            'long_text' => $data['long_text'] ?? null,
            'quantity' => $quantity,
            'unit' => $data['unit'] ?? null,
            'unit_price' => $unitPrice,
            'total_price' => $total,
            'currency' => $boq->currency,
            'is_addendum' => true,
            'position' => ((int) $boq->items()->max('position')) + 1,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Restleistung: abrechenbare Positionen mit offener Restmenge, die nicht
     * abgeschlossen/storniert/ersetzt sind.
     *
     * @return Collection<int, BoqItem>
     */
    public function remainingItems(BillOfQuantity $boq): Collection {
        return $boq->items()
            ->with('progress')
            ->get()
            ->filter(fn (BoqItem $item): bool => $item->type->isBillable()
                && !in_array($item->status, [BoqItemStatus::Completed, BoqItemStatus::Cancelled, BoqItemStatus::Replaced], true)
                && $item->remainingQuantity() > 0.0)
            ->values();
    }

    private function guard(BoqItemStatus $from, BoqItemStatus $to): void {
        if ($from === $to) {
            return;
        }
        if (!$this->canTransition($from, $to)) {
            throw new BoqWorkflowException(sprintf('Übergang %s → %s ist nicht erlaubt.', $from->value, $to->value));
        }
    }
}
