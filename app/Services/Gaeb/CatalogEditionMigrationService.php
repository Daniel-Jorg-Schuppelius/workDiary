<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogEditionMigrationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Models\{BillOfQuantity, BoqCatalog, BoqCatalogAssignment};
use App\Models\Catalog\{CatalogCodeMapping, CatalogEntry, CatalogRegistry};

/**
 * Ausgabenwechsel eines Kostengruppenkatalogs (Feature 109, MVP-641).
 *
 * **Ein Wechsel der Norm ist eine fachliche Entscheidung, keine
 * Datenmigration.** DIN 276:2018 hat die 200er, 500er und 600/700 neu
 * gegliedert; „370" (Baukonstruktive Einbauten, 2008) liegt 2018 unter „380",
 * und für manche Gruppen gibt es überhaupt keine Entsprechung.
 *
 * Der Dienst arbeitet deshalb in zwei getrennten Schritten:
 *
 * 1. **{@see preview()}** liefert ein Protokoll — je Zuordnung: was sie heute
 *    trägt, was sie tragen würde, und wo es **keine** Entsprechung gibt. Diese
 *    Lücken sind das Eigentliche: Sie zeigen, wo jemand entscheiden muss.
 * 2. **{@see apply()}** schreibt nur, was eindeutig ist, und meldet die Zahl
 *    der offen gebliebenen Fälle. Was ohne Entsprechung ist, bleibt
 *    unverändert stehen — eine geratene Nummer wäre schlimmer als die alte.
 */
final class CatalogEditionMigrationService {
    /**
     * Vorschau des Wechsels.
     *
     * @return array{
     *     rows: list<array{assignment_id: int, from: string, to: string|null, label: string|null, note: string|null}>,
     *     mapped: int,
     *     unmapped: int
     * }
     */
    public function preview(BillOfQuantity $boq, BoqCatalog $catalog, CatalogRegistry $from, CatalogRegistry $to): array {
        $mappings = CatalogCodeMapping::query()
            ->where('from_registry_id', $from->id)
            ->where('to_registry_id', $to->id)
            ->get()
            ->keyBy('from_code');

        $labels = CatalogEntry::query()
            ->where('catalog_registry_id', $to->id)
            ->pluck('label', 'code');

        $rows = [];
        $mapped = 0;
        $unmapped = 0;

        foreach (BoqCatalogAssignment::query()
            ->where('bill_of_quantity_id', $boq->id)
            ->where('catalog_key', $catalog->catalog_key)
            ->orderBy('code')
            ->get() as $assignment) {
            $mapping = $mappings->get(trim($assignment->code));
            $target = $mapping?->to_code;

            $rows[] = [
                'assignment_id' => (int) $assignment->id,
                'from' => trim($assignment->code),
                'to' => $target,
                'label' => $target === null ? null : ($labels[$target] ?? null),
                'note' => $mapping?->note,
            ];

            $target === null ? $unmapped++ : $mapped++;
        }

        return ['rows' => $rows, 'mapped' => $mapped, 'unmapped' => $unmapped];
    }

    /**
     * Führt den Wechsel durch — **nur für die eindeutigen Fälle**.
     *
     * Der Katalogkopf des Verzeichnisses wird auf die neue Ausgabe umgestellt,
     * damit Auswertung und Export dieselbe Sprache sprechen. Zuordnungen ohne
     * Entsprechung bleiben stehen und erscheinen in der Auswertung fortan als
     * unbekannte Nummer — sichtbar, statt still falsch.
     *
     * @return array{changed: int, unmapped: int}
     */
    public function apply(BillOfQuantity $boq, BoqCatalog $catalog, CatalogRegistry $from, CatalogRegistry $to): array {
        $preview = $this->preview($boq, $catalog, $from, $to);

        $changed = 0;
        foreach ($preview['rows'] as $row) {
            if ($row['to'] === null || $row['to'] === $row['from']) {
                continue;
            }
            BoqCatalogAssignment::query()->whereKey($row['assignment_id'])->update(['code' => $row['to']]);
            $changed++;
        }

        $catalog->update([
            'type' => $to->gaeb_type,
            'name' => $to->name,
        ]);

        return ['changed' => $changed, 'unmapped' => $preview['unmapped']];
    }
}
