<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceComparisonService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Models\{BillOfQuantity, BoqItemPriceSnapshot, GaebImport};
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Enums\GaebPhase;
use Illuminate\Support\Collection;

/**
 * Preisspiegel: mehrere Angebote zu einer Ausschreibung nebeneinander.
 *
 * Wer Nachunternehmer anfragt, verschickt eine X83 und bekommt mehrere X84
 * zurück. Der Vergleich braucht keine neue Datenhaltung — jeder Import legt
 * bereits je Position einen Preis-Schnappschuss ab; hier werden sie nur
 * nebeneinandergestellt.
 *
 * **Auffällig heißt nicht falsch.** Ein Preis weit unter den anderen kann ein
 * guter Einkauf sein oder ein Kalkulationsfehler; die Vergabeordnung verlangt
 * bei ungewöhnlich niedrigen Angeboten Aufklärung, nicht Ausschluss (§ 16d
 * VOB/A, § 60 VgV). Deshalb wird gekennzeichnet, nicht gewertet.
 */
final class PriceComparisonService {
    /**
     * Ab welchem Abstand zum nächstgünstigeren Angebot die Vergabeordnung eine
     * Aufklärung nahelegt — in der Praxis zehn Prozent.
     */
    private const UNUSUALLY_LOW_PERCENT = 10.0;

    /**
     * @return array{
     *     bidders: list<array{import_id: int, label: string, total: Money, rank: int, gap_percent: ?float, unusually_low: bool}>,
     *     rows: list<array{item_id: int, reference: string, short_text: ?string, quantity: ?string, unit: ?string, prices: array<int, Money>, cheapest_import_id: ?int, spread_percent: ?float}>,
     *     complete: bool
     * }
     */
    public function compare(BillOfQuantity $boq): array {
        $imports = $this->bidImports($boq);
        if ($imports->isEmpty()) {
            return ['bidders' => [], 'rows' => [], 'complete' => true];
        }

        $snapshots = BoqItemPriceSnapshot::query()
            ->whereIn('gaeb_import_id', $imports->pluck('id'))
            ->whereIn('boq_item_id', $boq->items()->select('id'))
            ->get()
            ->groupBy('boq_item_id');

        $rows = [];
        $totals = [];
        $complete = true;

        foreach ($boq->items()->orderBy('position')->get() as $item) {
            /** @var Collection<int, BoqItemPriceSnapshot> $forItem */
            $forItem = $snapshots->get($item->id, collect());

            $prices = [];
            foreach ($forItem as $snapshot) {
                if ($snapshot->unit_price === null) {
                    continue;
                }
                $prices[(int) $snapshot->gaeb_import_id] = $snapshot->unit_price;

                $total = $snapshot->total_price ?? $snapshot->unit_price;
                $key = (int) $snapshot->gaeb_import_id;
                $totals[$key] = isset($totals[$key]) ? $totals[$key]->plus($total) : $total;
            }

            // Eine Lücke ist kein Nullpreis: Wer nicht angeboten hat, gehört
            // nicht mit 0,00 € in den Vergleich.
            if (count($prices) < $imports->count()) {
                $complete = false;
            }

            $rows[] = [
                'item_id' => (int) $item->id,
                'reference' => (string) $item->reference_no,
                'short_text' => $item->short_text,
                'quantity' => $item->quantity === null ? null : (string) $item->quantity,
                'unit' => $item->unit,
                'prices' => $prices,
                'cheapest_import_id' => $this->cheapest($prices),
                'spread_percent' => $this->spread($prices),
            ];
        }

        return [
            'bidders' => $this->bidders($imports, $totals),
            'rows' => $rows,
            'complete' => $complete,
        ];
    }

    /**
     * Angebote zu diesem Verzeichnis. Nur Angebotsphasen zählen — die
     * Ausschreibung selbst ist kein Angebot.
     *
     * @return Collection<int, GaebImport>
     */
    private function bidImports(BillOfQuantity $boq): Collection {
        return GaebImport::query()
            ->where('bill_of_quantity_id', $boq->id)
            ->whereIn('phase', [GaebPhase::Bid->value, GaebPhase::SideBid->value])
            ->orderBy('id')
            ->get();
    }

    /**
     * Bieter mit Summe, Rang und Abstand zum jeweils nächstgünstigeren
     * Angebot.
     *
     * @param  Collection<int, GaebImport> $imports
     * @param  array<int, Money>           $totals
     * @return list<array{import_id: int, label: string, total: Money, rank: int, gap_percent: ?float, unusually_low: bool}>
     */
    private function bidders(Collection $imports, array $totals): array {
        $ranked = [];
        foreach ($imports as $import) {
            $key = (int) $import->id;
            if (!isset($totals[$key])) {
                continue;
            }
            // Einheitspreise führt GAEB auf ein Zehntelcent genau; eine
            // Angebotssumme wird kaufmännisch auf Cent gerundet.
            $total = $totals[$key]->withScale(2, RoundingMode::HalfUp);
            $ranked[] = ['import' => $import, 'total' => $total, 'value' => (float) $total->getAmount()];
        }

        usort($ranked, static fn (array $a, array $b): int => $a['value'] <=> $b['value']);

        $bidders = [];
        foreach ($ranked as $index => $entry) {
            $next = $ranked[$index + 1]['value'] ?? null;
            // Der Abstand misst sich zum nächstgünstigeren Angebot, nicht zum
            // Mittel: Genau darauf zielt die Aufklärungspflicht.
            $gap = $index === 0 && $next !== null && $next > 0.0
                ? round((($next - $entry['value']) / $next) * 100, 2)
                : null;

            $bidders[] = [
                'import_id' => (int) $entry['import']->id,
                'label' => (string) ($entry['import']->filename ?? $entry['import']->id),
                'total' => $entry['total'],
                'rank' => $index + 1,
                'gap_percent' => $gap,
                'unusually_low' => $gap !== null && $gap >= self::UNUSUALLY_LOW_PERCENT,
            ];
        }

        return $bidders;
    }

    /** @param array<int, Money> $prices */
    private function cheapest(array $prices): ?int {
        $cheapest = null;
        $lowest = null;

        foreach ($prices as $importId => $price) {
            $value = (float) $price->getAmount();
            if ($lowest === null || $value < $lowest) {
                $lowest = $value;
                $cheapest = $importId;
            }
        }

        return $cheapest;
    }

    /**
     * Spanne zwischen günstigstem und teuerstem Preis einer Position, in
     * Prozent des günstigsten. Sie zeigt, wo die Bieter die Leistung
     * unterschiedlich verstanden haben — oft ein Textproblem, kein Preisproblem.
     *
     * @param array<int, Money> $prices
     */
    private function spread(array $prices): ?float {
        if (count($prices) < 2) {
            return null;
        }

        $values = array_map(static fn (Money $price): float => (float) $price->getAmount(), array_values($prices));
        $lowest = min($values);
        if ($lowest <= 0.0) {
            return null;
        }

        return round(((max($values) - $lowest) / $lowest) * 100, 2);
    }
}
