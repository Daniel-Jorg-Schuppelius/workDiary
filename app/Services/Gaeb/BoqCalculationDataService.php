<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqCalculationDataService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\{BoqItemType, GaebPhase};
use App\Models\{BillOfQuantity, BoqCostType, BoqItem};

/**
 * Auswertung der GAEB-Kalkulationsdaten (X52, Feature 109, MVP-647):
 * **EKT und GKT je Position und je Kostenart**.
 *
 * - **EKT** — Einzelkosten der Teilleistung: was die Ansätze einer Position
 *   unmittelbar kosten, ohne Zuschlag.
 * - **GKT** — Gemeinkosten der Teilleistung: der Zuschlag darauf. **Er hängt
 *   an der Kostenart**, nicht am Ansatz: Ein Betrieb schlägt auf Lohn anders
 *   zu als auf Material, aber nicht je Position.
 *
 * Der eigentliche Wert der Auswertung ist die **Differenz zum angebotenen
 * Preis**: Eine Kalkulation, die vom Gesamtbetrag der Position abweicht, ist
 * entweder unvollständig übertragen oder bewusst korrigiert worden — beides
 * muss man sehen können.
 */
class BoqCalculationDataService {
    /**
     * @return array{rows: list<array{key: string, description: string|null, unit: string|null, markup: float|null, ekt: float, gkt: float, total: float}>, ekt: float, gkt: float, calculated: float, offered: float|null, delta: float|null, currency: string}
     */
    public function forItem(BoqItem $item): array {
        $item->loadMissing(['costApproaches', 'billOfQuantity.costTypes']);

        $bill = $item->billOfQuantity;
        /** @var iterable<BoqCostType> $types */
        $types = $bill === null ? [] : $bill->costTypes;
        $markups = [];
        $meta = [];
        foreach ($types as $type) {
            $markups[$type->cost_key] = $type->markup_percent === null ? null : (float) $type->markup_percent;
            $meta[$type->cost_key] = ['description' => $type->description, 'unit' => $type->unit];
        }

        $ektPerKey = [];
        foreach ($item->costApproaches as $approach) {
            $amount = $approach->calculatedAmount();
            if ($amount === null) {
                continue;
            }
            $ektPerKey[$approach->cost_key] = ($ektPerKey[$approach->cost_key] ?? 0.0) + $amount;
        }

        ksort($ektPerKey);

        $rows = [];
        $ektTotal = 0.0;
        $gktTotal = 0.0;
        foreach ($ektPerKey as $key => $ekt) {
            $markup = $markups[(string) $key] ?? null;
            // Ohne Zuschlagssatz gibt es keinen Zuschlag - eine unterstellte
            // Null wäre dasselbe Ergebnis, aber ein anderer Satz: Sie behauptete,
            // der Betrieb schlage auf diese Art nichts zu.
            $gkt = $markup === null ? 0.0 : $ekt * $markup / 100;

            $rows[] = [
                'key' => (string) $key,
                'description' => $meta[(string) $key]['description'] ?? null,
                'unit' => $meta[(string) $key]['unit'] ?? null,
                'markup' => $markup,
                'ekt' => round($ekt, 2),
                'gkt' => round($gkt, 2),
                'total' => round($ekt + $gkt, 2),
            ];

            $ektTotal += $ekt;
            $gktTotal += $gkt;
        }

        $calculated = $ektTotal + $gktTotal;
        $offered = $item->total_price?->toFloat();

        return [
            'rows' => $rows,
            'ekt' => round($ektTotal, 2),
            'gkt' => round($gktTotal, 2),
            'calculated' => round($calculated, 2),
            'offered' => $offered === null ? null : round($offered, 2),
            // Ohne Angebotspreis gibt es nichts zu vergleichen - eine Null
            // behauptete Übereinstimmung.
            'delta' => $offered === null ? null : round($calculated - $offered, 2),
            'currency' => $item->currency->value,
        ];
    }

    /**
     * Dieselbe Rechnung über das ganze Verzeichnis, zusätzlich je Kostenart
     * zusammengefasst.
     *
     * @return array{items: list<array{item: BoqItem, ekt: float, gkt: float, calculated: float, offered: float|null, delta: float|null}>, byCostType: list<array{key: string, description: string|null, ekt: float, gkt: float, total: float}>, ekt: float, gkt: float, calculated: float, offered: float, delta: float, unpriced: int, currency: string}
     */
    public function forBill(BillOfQuantity $boq): array {
        $boq->loadMissing(['costTypes']);

        $items = BoqItem::query()
            ->where('bill_of_quantity_id', $boq->id)
            ->whereHas('costApproaches')
            ->with(['costApproaches'])
            ->orderBy('position')
            ->get();

        $markups = [];
        $meta = [];
        foreach ($boq->costTypes as $type) {
            $markups[$type->cost_key] = $type->markup_percent === null ? null : (float) $type->markup_percent;
            $meta[$type->cost_key] = $type->description;
        }

        $rows = [];
        $perKey = [];
        $ektTotal = 0.0;
        $gktTotal = 0.0;
        $offeredTotal = 0.0;
        $unpriced = 0;

        foreach ($items as $item) {
            $ekt = 0.0;
            $gkt = 0.0;
            foreach ($item->costApproaches as $approach) {
                $amount = $approach->calculatedAmount();
                if ($amount === null) {
                    continue;
                }
                $markup = $markups[$approach->cost_key] ?? null;
                $share = $markup === null ? 0.0 : $amount * $markup / 100;

                $ekt += $amount;
                $gkt += $share;
                $perKey[$approach->cost_key]['ekt'] = ($perKey[$approach->cost_key]['ekt'] ?? 0.0) + $amount;
                $perKey[$approach->cost_key]['gkt'] = ($perKey[$approach->cost_key]['gkt'] ?? 0.0) + $share;
            }

            $offered = $item->total_price?->toFloat();
            $rows[] = [
                'item' => $item,
                'ekt' => round($ekt, 2),
                'gkt' => round($gkt, 2),
                'calculated' => round($ekt + $gkt, 2),
                'offered' => $offered === null ? null : round($offered, 2),
                'delta' => $offered === null ? null : round($ekt + $gkt - $offered, 2),
            ];

            $ektTotal += $ekt;
            $gktTotal += $gkt;
            // Eine Position ohne Preis wird gezählt, nicht als 0 € verbucht:
            // Sonst stammte die Gesamtdifferenz aus fehlenden Preisen und sähe
            // wie ein Kalkulationsfehler aus.
            if ($offered === null) {
                $unpriced++;
            } else {
                $offeredTotal += $offered;
            }
        }

        ksort($perKey);
        $byCostType = [];
        foreach ($perKey as $key => $sums) {
            $byCostType[] = [
                'key' => (string) $key,
                'description' => $meta[(string) $key] ?? null,
                'ekt' => round($sums['ekt'], 2),
                'gkt' => round($sums['gkt'], 2),
                'total' => round($sums['ekt'] + $sums['gkt'], 2),
            ];
        }

        return [
            'items' => $rows,
            'byCostType' => $byCostType,
            'ekt' => round($ektTotal, 2),
            'gkt' => round($gktTotal, 2),
            'calculated' => round($ektTotal + $gktTotal, 2),
            'offered' => round($offeredTotal, 2),
            'delta' => round($ektTotal + $gktTotal - $offeredTotal, 2),
            'unpriced' => $unpriced,
            'currency' => $boq->currency->value,
        ];
    }

    /**
     * Kalkulierte Kosten je LV-Position, **auf die aufgemessene Menge
     * skaliert** — die Grundlage für den Plan-Ist-Vergleich der
     * Nachkalkulation (Feature 014).
     *
     * Die Kalkulation gilt der vollen LV-Menge; die Ist-Kosten fallen für die
     * bisher ausgeführte an. Beide unskaliert zu vergleichen hieße, jeden noch
     * nicht fertigen Bauabschnitt als Ersparnis auszuweisen. Ohne LV-Menge
     * lässt sich nicht skalieren — dann gibt es **keinen** Wert, nicht null
     * Euro.
     *
     * @param  list<int> $itemIds
     * @return array<int, float> Positions-ID → kalkulierte Kosten je Mengeneinheit
     */
    public function unitCostsFor(BillOfQuantity $boq, array $itemIds): array {
        if ($itemIds === []) {
            return [];
        }

        $markups = [];
        foreach ($boq->costTypes as $type) {
            $markups[$type->cost_key] = $type->markup_percent === null ? null : (float) $type->markup_percent;
        }

        $items = BoqItem::query()
            ->whereIn('id', $itemIds)
            ->whereHas('costApproaches')
            ->with(['costApproaches'])
            ->get();

        $unitCosts = [];
        foreach ($items as $item) {
            $quantity = $item->quantity?->getValue()->toFloat();
            if ($quantity === null || $quantity == 0.0) {
                continue;
            }

            $total = 0.0;
            foreach ($item->costApproaches as $approach) {
                $amount = $approach->calculatedAmount();
                if ($amount === null) {
                    continue;
                }
                $markup = $markups[$approach->cost_key] ?? null;
                $total += $markup === null ? $amount : $amount * (1 + $markup / 100);
            }

            if ($total !== 0.0) {
                $unitCosts[(int) $item->id] = $total / $quantity;
            }
        }

        return $unitCosts;
    }

    /**
     * Woher die Kalkulation stammt: aus einer eingelesenen X52 oder aus dem
     * eigenen Haus.
     *
     * Die Unterscheidung gehört an jede Auswertung, die sie als Plan-Wert
     * verwendet — eine fremde Kalkulation ist die Rechnung eines anderen
     * Betriebs, nicht die eigene Planung.
     */
    public function calculationIsImported(BillOfQuantity $boq): bool {
        return $boq->imports()
            ->where('phase', GaebPhase::CalculationData->value)
            ->exists();
    }

    /**
     * Zuschlagspositionen dürfen **keine** Kostenansätze tragen (X52-Regel).
     *
     * Der Grund ist inhaltlich: Eine Zuschlagsposition rechnet prozentual auf
     * andere Positionen — trüge sie eigene Ansätze, zählte dasselbe Geld
     * zweimal. Beanstandet wird, nicht stillschweigend bereinigt: Die Datei
     * kommt aus einem fremden System, und was dort steht, ist dessen Aussage.
     *
     * @return list<string> Ordnungszahlen der beanstandeten Positionen
     */
    public function markupItemsWithApproaches(BillOfQuantity $boq): array {
        return array_values(BoqItem::query()
            ->where('bill_of_quantity_id', $boq->id)
            ->where('type', BoqItemType::Markup->value)
            ->whereHas('costApproaches')
            ->orderBy('position')
            ->pluck('reference_no')
            ->map(strval(...))
            ->all());
    }
}
