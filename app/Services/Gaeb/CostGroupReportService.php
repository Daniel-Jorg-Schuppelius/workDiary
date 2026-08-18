<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostGroupReportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Models\{BillOfQuantity, BoqCatalog, BoqCatalogAssignment, BoqItem, BoqItemQuantitySplit};
use App\Models\Catalog\{CatalogEntry, CatalogRegistry};
use App\Models\Costing\CostEstimate;

/**
 * Kostengruppen-Auswertung eines Leistungsverzeichnisses (Feature 109,
 * MVP-642).
 *
 * Die Summe je Kostengruppe wird **jederzeit aus den Positionen abgeleitet**
 * und nirgends als eigener Kostenstand fortgeschrieben (D4). Wer sie
 * fortschriebe, hätte zwei Wahrheiten, sobald jemand einen Preis ändert.
 *
 * Drei Regeln bestimmen, was in welche Gruppe fällt:
 *
 * 1. **Die Teilmenge schlägt die Position.** Ist eine Position aufgeteilt
 *    (300 m³ auf KG 310, 150 m³ auf KG 320), zählt die Aufteilung — sonst
 *    landete der ganze Betrag in einer Gruppe, in die er nur zum Teil gehört.
 * 2. **Der Abschnitt vererbt an seine Positionen**, aber nur, wo die Position
 *    selbst nichts sagt. Eine Zuordnung an der Position ist die genauere
 *    Angabe.
 * 3. **Was ohne Zuordnung bleibt, wird ausgewiesen**, nicht verteilt und nicht
 *    verschwiegen. Ein stillschweigend auf null gesetzter Rest macht jede
 *    Auswertung wertlos.
 */
final class CostGroupReportService {
    /**
     * Summen je Kostengruppe für ein Leistungsverzeichnis.
     *
     * @param  int $level Gliederungstiefe: 1 = Hunderter (300), 2 = Zehner (310), 3 = volle Nummer
     * @return array{
     *     catalog: BoqCatalog|null,
     *     registry: CatalogRegistry|null,
     *     rows: list<array{code: string, label: string, amount: float, share: float}>,
     *     unassigned: float,
     *     total: float
     * }
     */
    public function forBill(BillOfQuantity $boq, ?string $catalogKey = null, int $level = 2): array {
        $catalog = $this->costGroupCatalog($boq, $catalogKey);
        $registry = $catalog === null ? null : $this->registryFor($catalog);

        $items = BoqItem::query()
            ->where('bill_of_quantity_id', $boq->id)
            ->with(['catalogAssignments', 'quantitySplits.catalogAssignments'])
            ->get();

        $sectionCodes = $catalog === null ? [] : $this->sectionCodes($boq, $catalog->catalog_key);

        $sums = [];
        $unassigned = 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            $amount = $this->amountOf($item);
            if ($amount === 0.0) {
                continue;
            }
            $total += $amount;

            if ($catalog === null) {
                $unassigned += $amount;

                continue;
            }

            $shares = $this->sharesOf($item, $catalog->catalog_key, $sectionCodes);
            if ($shares === []) {
                $unassigned += $amount;

                continue;
            }

            foreach ($shares as $code => $factor) {
                // PHP macht aus numerischen Array-Schlüsseln int - die
                // Kostengruppe bleibt aber ein Code, keine Zahl.
                $key = $this->truncate((string) $code, $level);
                $sums[$key] = ($sums[$key] ?? 0.0) + $amount * $factor;
            }

            // Ein Rest bleibt, wenn die Teilmengen die Positionsmenge nicht
            // ausschöpfen - er gehört ausgewiesen, nicht verteilt.
            $assigned = array_sum($shares);
            if ($assigned < 1.0) {
                $unassigned += $amount * (1.0 - $assigned);
            }
        }

        ksort($sums);
        $labels = $registry === null ? [] : $this->labels($registry, array_keys($sums));

        $rows = [];
        foreach ($sums as $code => $amount) {
            $rows[] = [
                'code' => (string) $code,
                'label' => $labels[(string) $code] ?? (string) __('Unbekannte Kostengruppe'),
                'amount' => round($amount, 2),
                'share' => $total > 0.0 ? round($amount / $total * 100, 1) : 0.0,
            ];
        }

        return [
            'catalog' => $catalog,
            'registry' => $registry,
            'rows' => $rows,
            'unassigned' => round($unassigned, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Kostenverfolgung über den Lebenszyklus (MVP-643): je Kostengruppe der
     * LV-Ansatz, die Nachträge, die aufgemessene Leistung und der Rest.
     *
     * **Das Budget stammt aus der jüngsten Kostenermittlung des Projekts**
     * (MVP-646) — liegt keine vor, bleibt die Spalte `null` statt 0,00 €: Ein
     * fehlendes Budget ist kein Budget von null. Der **abgerechnete Stand**
     * fehlt weiterhin bewusst; er liegt im führenden Faktura-System
     * (Feature 108, D8).
     *
     * **Abweichungen werden nicht geglättet** (D4): Ein Aufmaß über der
     * LV-Menge ergibt einen negativen Rest, und genau das gehört gezeigt.
     *
     * @return array{
     *     rows: list<array{code: string, label: string, budget: float|null, boq: float, addenda: float, executed: float, remaining: float}>,
     *     totals: array{budget: float|null, boq: float, addenda: float, executed: float, remaining: float},
     *     estimate: \App\Models\Costing\CostEstimate|null
     * }
     */
    public function lifecycle(BillOfQuantity $boq, ?string $catalogKey = null, int $level = 2): array {
        $catalog = $this->costGroupCatalog($boq, $catalogKey);
        $registry = $catalog === null ? null : $this->registryFor($catalog);

        $items = BoqItem::query()
            ->where('bill_of_quantity_id', $boq->id)
            ->with(['catalogAssignments', 'quantitySplits.catalogAssignments'])
            ->get();
        $sectionCodes = $catalog === null ? [] : $this->sectionCodes($boq, $catalog->catalog_key);

        $estimate = $this->budgetEstimate($boq);
        $budgets = $estimate === null ? [] : $this->budgetsOf($estimate, $level);

        $rows = [];
        $totals = ['boq' => 0.0, 'addenda' => 0.0, 'executed' => 0.0, 'remaining' => 0.0];

        foreach ($items as $item) {
            $price = $item->unit_price?->toFloat();
            if ($price === null) {
                continue;
            }
            $planned = round(($item->quantity?->getValue()->toFloat() ?? 0.0) * $price, 2);
            $executed = round($item->executedQuantity() * $price, 2);
            if ($planned === 0.0 && $executed === 0.0) {
                continue;
            }

            $shares = $catalog === null ? [] : $this->sharesOf($item, $catalog->catalog_key, $sectionCodes);
            if ($shares === []) {
                // „Ohne Zuordnung" ist eine eigene Zeile, kein Auslassen.
                $shares = ['' => 1.0];
            }

            foreach ($shares as $code => $factor) {
                $key = $code === '' ? '' : $this->truncate((string) $code, $level);
                $rows[$key] ??= ['boq' => 0.0, 'addenda' => 0.0, 'executed' => 0.0];
                // Nachtragspositionen zählen getrennt: Der LV-Ansatz ist das,
                // was ausgeschrieben war - der Nachtrag kam hinzu.
                $rows[$key][$item->is_addendum ? 'addenda' : 'boq'] += $planned * $factor;
                $rows[$key]['executed'] += $executed * $factor;
            }
        }

        ksort($rows);
        $labels = $registry === null ? [] : $this->labels($registry, array_keys($rows));

        $out = [];
        foreach ($rows as $code => $row) {
            $planned = round($row['boq'] + $row['addenda'], 2);
            $executed = round($row['executed'], 2);
            $out[] = [
                'code' => (string) $code,
                'label' => $code === '' ? (string) __('Ohne Zuordnung') : ($labels[(string) $code] ?? (string) __('Unbekannte Kostengruppe')),
                'budget' => $estimate === null ? null : round($budgets[(string) $code] ?? 0.0, 2),
                'boq' => round($row['boq'], 2),
                'addenda' => round($row['addenda'], 2),
                'executed' => $executed,
                'remaining' => round($planned - $executed, 2),
            ];
            $totals['boq'] += $row['boq'];
            $totals['addenda'] += $row['addenda'];
            $totals['executed'] += $row['executed'];
        }
        $totals = array_map(static fn (float $value): float => round($value, 2), $totals);
        $totals['remaining'] = round($totals['boq'] + $totals['addenda'] - $totals['executed'], 2);
        // Die Budgetsumme kommt aus der Ermittlung selbst, nicht aus den
        // Zeilen: Sie enthält auch Kostengruppen, die im LV nicht vorkommen -
        // genau deren Fehlen ist eine Aussage.
        $totals['budget'] = $estimate === null ? null : round((float) $estimate->items()->sum('amount'), 2);

        return ['rows' => $out, 'totals' => $totals, 'estimate' => $estimate];
    }

    /**
     * Kostengruppen-Pivot mit aufklappbaren Ebenen (Feature 109, MVP-644).
     *
     * Statt einer flachen Liste je gewählter Ebene liefert der Pivot **alle
     * drei Ebenen ineinander**: 300 → 310 → 311. Wer eine Summe prüft, will
     * sehen, woraus sie besteht, ohne die Ansicht umzuschalten und dabei den
     * Zusammenhang zu verlieren.
     *
     * Die Summen der Oberebenen entstehen dabei **aus ihren Kindern**, nicht
     * aus einer zweiten Rechnung — sonst könnten Ebene und Summe auseinander
     * laufen.
     *
     * @return array{rows: list<array{code: string, label: string, level: int, amount: float, share: float, children: list<mixed>}>, unassigned: float, total: float}
     */
    public function pivot(BillOfQuantity $boq, ?string $catalogKey = null): array {
        // Die feinste Ebene ist die Wahrheit; alles darüber wird gefaltet.
        $leaves = $this->forBill($boq, $catalogKey, 3);
        $catalog = $this->costGroupCatalog($boq, $catalogKey);
        $registry = $catalog === null ? null : $this->registryFor($catalog);

        $tree = [];
        foreach ($leaves['rows'] as $row) {
            $code = $row['code'];
            $digits = preg_replace('/\D/', '', $code) ?? '';
            if ($digits === '') {
                // Eine Nummer ohne Ziffern lässt sich nicht falten; sie bleibt
                // als eigene Wurzel stehen, statt aus der Summe zu fallen.
                $tree[$code]['amount'] = ($tree[$code]['amount'] ?? 0.0) + $row['amount'];
                $tree[$code]['children'] ??= [];

                continue;
            }

            // Gefaltet wird mit derselben Regel wie in forBill() - zwei
            // Faltregeln könnten auseinanderlaufen, und niemand sähe es.
            $first = $this->truncate($code, 1);
            $second = $this->truncate($code, 2);

            $tree[$first]['amount'] = ($tree[$first]['amount'] ?? 0.0) + $row['amount'];
            $tree[$first]['children'][$second]['amount'] = ($tree[$first]['children'][$second]['amount'] ?? 0.0) + $row['amount'];

            // Die dritte Ebene nur, wo sie sich von der zweiten unterscheidet —
            // sonst stünde dieselbe Zahl zweimal untereinander.
            if ($code !== $second) {
                $tree[$first]['children'][$second]['children'][$code] = $row['amount'];
            }
        }

        ksort($tree);
        $labels = $registry === null ? [] : $this->labels($registry, $this->allCodes($tree));
        $total = $leaves['total'];

        $out = [];
        foreach ($tree as $firstCode => $firstNode) {
            $secondOut = [];
            ksort($firstNode['children']);
            foreach ($firstNode['children'] as $secondCode => $secondNode) {
                $thirdOut = [];
                $third = $secondNode['children'] ?? [];
                ksort($third);
                foreach ($third as $thirdCode => $amount) {
                    $thirdOut[] = $this->pivotRow((string) $thirdCode, 3, $amount, $total, $labels);
                }
                $secondOut[] = $this->pivotRow((string) $secondCode, 2, $secondNode['amount'], $total, $labels, $thirdOut);
            }
            $out[] = $this->pivotRow((string) $firstCode, 1, $firstNode['amount'], $total, $labels, $secondOut);
        }

        return [
            'rows' => $out,
            // „Ohne Zuordnung" gehört auch hier in die Tabelle - sonst summierte
            // sich der Pivot auf weniger als das Verzeichnis.
            'unassigned' => $leaves['unassigned'],
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, string>       $labels
     * @param  list<mixed>                 $children
     * @return array{code: string, label: string, level: int, amount: float, share: float, children: list<mixed>}
     */
    private function pivotRow(string $code, int $level, float $amount, float $total, array $labels, array $children = []): array {
        return [
            'code' => $code,
            'label' => $labels[$code] ?? (string) __('Unbekannte Kostengruppe'),
            'level' => $level,
            'amount' => round($amount, 2),
            'share' => $total > 0.0 ? round($amount / $total * 100, 1) : 0.0,
            'children' => $children,
        ];
    }

    /**
     * Alle Nummern des Baums — für einen einzigen Label-Zugriff statt einem je
     * Ebene.
     *
     * @param  array<string, mixed> $tree
     * @return list<string>
     */
    private function allCodes(array $tree): array {
        $codes = [];
        foreach ($tree as $first => $firstNode) {
            $codes[] = (string) $first;
            foreach ($firstNode['children'] ?? [] as $second => $secondNode) {
                $codes[] = (string) $second;
                foreach (array_keys($secondNode['children'] ?? []) as $third) {
                    $codes[] = (string) $third;
                }
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Die Kostenermittlung, gegen die verglichen wird: die **jüngste** des
     * Projekts, an dem das Verzeichnis hängt.
     *
     * Ohne Projekt gibt es kein Budget — ein Leistungsverzeichnis allein ist
     * kein Bauvorhaben, und die Ermittlung eines fremden Projekts danebenzu-
     * legen wäre schlicht falsch.
     */
    public function budgetEstimate(BillOfQuantity $boq): ?CostEstimate {
        if ($boq->project_id === null) {
            return null;
        }

        return CostEstimate::query()
            ->where('project_id', $boq->project_id)
            // Die eigene Ableitung ist kein Budget, sondern ihr Gegenstück.
            ->where('source', '!=', CostEstimate::SOURCE_DERIVED)
            ->orderByDesc('determined_on')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Budgetbeträge je Kostengruppe, auf die gewünschte Ebene zusammengefasst.
     *
     * Gezählt werden **nur Blattelemente** — eine Ermittlung führt Ober- und
     * Untergruppen nebeneinander, und beide zu summieren zählte dasselbe Geld
     * zweimal.
     *
     * @return array<string, float>
     */
    private function budgetsOf(CostEstimate $estimate, int $level): array {
        $items = $estimate->items()->get();
        $parents = $items->pluck('parent_code')->filter()->unique()->all();

        $budgets = [];
        foreach ($items as $item) {
            $code = trim((string) $item->code);
            if ($item->amount === null) {
                continue;
            }
            if ($code !== '' && in_array($code, $parents, true)) {
                continue;
            }
            $key = $code === '' ? '' : $this->truncate($code, $level);
            $budgets[$key] = ($budgets[$key] ?? 0.0) + (float) $item->amount;
        }

        return $budgets;
    }

    /**
     * Der Kostengruppenkatalog des Verzeichnisses. Ohne ausdrückliche Wahl der
     * erste — ein LV führt selten mehrere.
     */
    public function costGroupCatalog(BillOfQuantity $boq, ?string $catalogKey = null): ?BoqCatalog {
        $catalogs = BoqCatalog::query()->where('bill_of_quantity_id', $boq->id)->get();

        if ($catalogKey !== null) {
            return $catalogs->firstWhere('catalog_key', $catalogKey);
        }

        return $catalogs->first(static fn (BoqCatalog $catalog): bool => $catalog->isCostGroup());
    }

    /**
     * Der Stamm, der zum Katalogtyp der Datei passt — er liefert die
     * Kurzbezeichnungen. Ohne Treffer bleiben die Nummern für sich stehen; sie
     * zu erfinden wäre schlimmer als sie nackt zu zeigen.
     */
    public function registryFor(BoqCatalog $catalog): ?CatalogRegistry {
        $type = trim((string) $catalog->type);
        if ($type === '') {
            return null;
        }

        return CatalogRegistry::query()->where('gaeb_type', $type)->where('active', true)->first();
    }

    /**
     * Anteile einer Position je Kostengruppe: Schlüssel ist der Code, Wert der
     * Anteil zwischen 0 und 1.
     *
     * @param  array<int, string>   $sectionCodes Abschnitt-ID → geerbter Code
     * @return array<string, float>
     */
    private function sharesOf(BoqItem $item, string $catalogKey, array $sectionCodes): array {
        $splits = $item->quantitySplits;
        $itemQuantity = (float) ($item->quantity?->getValue()->toFloat() ?? 0.0);

        // 1. Teilmengen schlagen die Position.
        if ($splits->isNotEmpty()) {
            $shares = [];
            foreach ($splits as $split) {
                $code = $this->codeOf($split->catalogAssignments, $catalogKey);
                if ($code === null) {
                    continue;
                }
                $factor = $this->factorOf($split, $itemQuantity);
                if ($factor > 0.0) {
                    $shares[$code] = ($shares[$code] ?? 0.0) + $factor;
                }
            }
            if ($shares !== []) {
                return $shares;
            }
        }

        // 2. Zuordnung an der Position.
        $code = $this->codeOf($item->catalogAssignments, $catalogKey);
        if ($code !== null) {
            return [$code => 1.0];
        }

        // 3. Vererbung vom Abschnitt.
        $sectionId = $item->boq_section_id;
        if ($sectionId !== null && isset($sectionCodes[$sectionId])) {
            return [$sectionCodes[$sectionId] => 1.0];
        }

        return [];
    }

    /** Anteil einer Teilmenge an der Position: Prozent hat Vorrang vor Menge. */
    private function factorOf(BoqItemQuantitySplit $split, float $itemQuantity): float {
        if ($split->percent !== null) {
            return (float) $split->percent / 100.0;
        }
        if ($split->quantity !== null && $itemQuantity > 0.0) {
            return (float) $split->quantity / $itemQuantity;
        }

        return 0.0;
    }

    /**
     * @param \Illuminate\Support\Collection<int, BoqCatalogAssignment> $assignments
     */
    private function codeOf(\Illuminate\Support\Collection $assignments, string $catalogKey): ?string {
        $match = $assignments->first(
            static fn (BoqCatalogAssignment $assignment): bool => $assignment->catalog_key === $catalogKey
        );

        return $match === null ? null : trim($match->code);
    }

    /**
     * Zuordnungen der Abschnitte, damit Positionen ohne eigene Angabe erben.
     *
     * @return array<int, string>
     */
    private function sectionCodes(BillOfQuantity $boq, string $catalogKey): array {
        $codes = [];
        foreach (BoqCatalogAssignment::query()
            ->where('bill_of_quantity_id', $boq->id)
            ->where('catalog_key', $catalogKey)
            ->where('assignable_type', \App\Models\BoqSection::class)
            ->get() as $assignment) {
            $codes[(int) $assignment->assignable_id] = trim($assignment->code);
        }

        return $codes;
    }

    /** Gesamtbetrag einer Position — Menge × Einheitspreis. */
    private function amountOf(BoqItem $item): float {
        $quantity = $item->quantity?->getValue()->toFloat();
        $price = $item->unit_price?->toFloat();
        if ($quantity === null || $price === null) {
            return 0.0;
        }

        return round($quantity * $price, 2);
    }

    /** „311" auf Ebene 2 ist „310", auf Ebene 1 „300". */
    private function truncate(string $code, int $level): string {
        $digits = preg_replace('/\D/', '', $code) ?? $code;
        if ($digits === '' || $level >= 3 || strlen($digits) <= $level) {
            return $code;
        }

        return str_pad(substr($digits, 0, $level), strlen($digits), '0');
    }

    /**
     * @param  list<array-key>      $codes
     * @return array<string, string>
     */
    private function labels(CatalogRegistry $registry, array $codes): array {
        $labels = [];
        foreach (CatalogEntry::query()
            ->where('catalog_registry_id', $registry->id)
            ->whereIn('code', array_map(strval(...), $codes))
            ->get() as $entry) {
            $labels[$entry->code] = $entry->localizedLabel();
        }

        return $labels;
    }
}
