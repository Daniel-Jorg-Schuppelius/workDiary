<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Models\{BillOfQuantity, BoqCatalog, BoqCatalogAssignment, BoqItem};
use App\Models\Catalog\CatalogAssignmentRule;

/**
 * Vorschlagsregeln anwenden (Feature 109, MVP-640).
 *
 * Ein Betrieb ordnet immer wieder dieselben Leistungen denselben Kostengruppen
 * zu. Die Regeln halten dieses Wissen fest — **als Vorschlag**:
 *
 * - **Was schon zugeordnet ist, bleibt.** Weder eine importierte noch eine von
 *   Hand gesetzte Zuordnung wird überschrieben; der Lauf füllt nur Lücken.
 *   Wer eine bestehende Zuordnung ändern will, tut das bewusst über die
 *   Massenzuordnung.
 * - **Gesetzt wird mit `source = 'rule'`.** Nur so lässt sich später sagen,
 *   worauf eine Auswertung beruht — und nur so kann ein späterer Lauf einen
 *   eigenen Vorschlag wieder korrigieren, ohne fremde Arbeit anzutasten.
 * - **Die erste greifende Regel gewinnt.** Zwei Vorschläge für dieselbe
 *   Position wären keine Hilfe, sondern eine Rückfrage.
 *
 * Der Leistungsbereich schlägt das Stichwort: Er steht in der Datei, das
 * Stichwort ist eine Vermutung über den Text.
 */
final class CatalogSuggestionService {
    public function __construct(private readonly CatalogAssignmentService $assignments) {}

    /**
     * Wendet die Regeln der Organisation auf ein Leistungsverzeichnis an.
     *
     * @return array{applied: int, skipped: int}
     */
    public function apply(BillOfQuantity $boq, ?BoqCatalog $catalog = null): array {
        $catalog ??= $this->assignments->costGroupCatalog($boq);
        if ($catalog === null) {
            return ['applied' => 0, 'skipped' => 0];
        }

        $registry = $this->assignments->registryFor($catalog);
        if ($registry === null) {
            // Ohne Stamm wüssten wir nicht, ob ein Regel-Code zu diesem Katalog
            // überhaupt gehört.
            return ['applied' => 0, 'skipped' => 0];
        }

        $rules = CatalogAssignmentRule::query()
            ->where('active', true)
            ->where('catalog_registry_id', $registry->id)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
        if ($rules->isEmpty()) {
            return ['applied' => 0, 'skipped' => 0];
        }

        $assigned = $this->assignedItemIds($boq, $catalog);
        $applied = 0;
        $skipped = 0;

        foreach (BoqItem::query()->where('bill_of_quantity_id', $boq->id)->get() as $item) {
            if (isset($assigned[$item->id])) {
                $skipped++;

                continue;
            }

            $code = $this->suggest($item, $rules);
            if ($code === null) {
                $skipped++;

                continue;
            }

            $this->assignments->assign($item, $catalog, $code, CatalogAssignmentService::SOURCE_RULE);
            $applied++;
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * Vorschlag für eine einzelne Position — `null`, wenn keine Regel greift.
     *
     * @param \Illuminate\Support\Collection<int, CatalogAssignmentRule> $rules
     */
    public function suggest(BoqItem $item, \Illuminate\Support\Collection $rules): ?string {
        $workCategory = $this->workCategoryOf($item);
        $text = mb_strtolower(trim((string) $item->short_text . ' ' . (string) $item->long_text));

        foreach ($rules as $rule) {
            $matched = match ($rule->match_type) {
                // Der Leistungsbereich steht in der Datei: Er wird auf Präfix
                // verglichen, weil „013" auch „013.2" einschließt.
                CatalogAssignmentRule::MATCH_WORK_CATEGORY => $workCategory !== null
                    && str_starts_with($workCategory, trim($rule->match_value)),
                CatalogAssignmentRule::MATCH_KEYWORD => $text !== ''
                    && str_contains($text, mb_strtolower(trim($rule->match_value))),
                default => false,
            };

            if ($matched) {
                return $rule->code;
            }
        }

        return null;
    }

    /**
     * Der Leistungsbereich der Position — er steckt als Katalogzuordnung an
     * ihr, unter einem Katalog vom Typ `work category`.
     */
    private function workCategoryOf(BoqItem $item): ?string {
        $keys = BoqCatalog::query()
            ->where('bill_of_quantity_id', $item->bill_of_quantity_id)
            ->where('type', 'work category')
            ->pluck('catalog_key')
            ->all();
        if ($keys === []) {
            return null;
        }

        $assignment = BoqCatalogAssignment::query()
            ->where('assignable_type', $item->getMorphClass())
            ->where('assignable_id', $item->id)
            ->whereIn('catalog_key', $keys)
            ->first();

        return $assignment === null ? null : trim($assignment->code);
    }

    /**
     * Positionen, die für diesen Katalog schon etwas tragen.
     *
     * @return array<int, true>
     */
    private function assignedItemIds(BillOfQuantity $boq, BoqCatalog $catalog): array {
        $ids = [];
        foreach (BoqCatalogAssignment::query()
            ->where('bill_of_quantity_id', $boq->id)
            ->where('catalog_key', $catalog->catalog_key)
            ->where('assignable_type', (new BoqItem)->getMorphClass())
            ->pluck('assignable_id') as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
    }
}
