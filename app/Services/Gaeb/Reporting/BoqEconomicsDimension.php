<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqEconomicsDimension.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb\Reporting;

use App\Models\Gaeb\{BillOfQuantity, BoqItem, BoqItemMapping, BoqItemProgress};
use App\Models\Material\{Material, MaterialUsage};
use App\Models\Time\{TimeEntry, Timesheet};
use App\Services\Billing\DocumentTotalsCalculator;
use App\Services\Gaeb\BoqCalculationDataService;
use App\Services\Reporting\Contracts\ProjectEconomicsDimension;
use App\Services\Reporting\EconomicsReportBuilder;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * LV-Dimension des Wirtschaftlichkeitsberichts (Feature 049/108): Erlös je
 * Position aus aufgemessener Menge × Einheitspreis, Kosten über die
 * Positionszuordnung von Zeiten und Material; Dimension `boq`.
 */
final class BoqEconomicsDimension implements ProjectEconomicsDimension {
    public function __construct(private readonly EconomicsReportBuilder $economics) {}

    public function key(): string {
        return 'boq';
    }

    /**
     * LV-Dimension (MVP-332, Feature 014 × 049): Kosten/Erlöse je LV-Position
     * (Ordnungszahl) eines Projekts mit Leistungsverzeichnis.
     *
     * Erlös je Position = im Zeitraum aufgemessene Menge (BoqItemProgress) ×
     * Einheitspreis — die Projektion der Abrechnung nach Aufmaß (vgl.
     * {@see \App\Services\Gaeb\BoqCostingService}); nur mengen-/preisbasiert
     * abrechenbare Positionsarten mit gepflegtem EP tragen Erlös.
     *
     * Kosten je Position werden über die ECHTEN Verknüpfungen zugeordnet:
     *  - TimeEntry → Bautagebuch-Eintrag → Aufmaß-Meldung (diary_entry_id),
     *  - MaterialUsage → direkte Aufmaß-Verknüpfung (material_usage_id) bzw.
     *    Positions-Mapping des Materialstamms (BoqItemMapping).
     * Mehrdeutige Verknüpfungen (Quellposten zeigt auf mehrere Positionen)
     * werden NICHT still aufgeteilt, sondern als „ohne Zuordnung" geführt —
     * ebenso Quellposten ganz ohne LV-Bezug (u. a. alle Spesen, denen das
     * Datenmodell keinen LV-Anker gibt). Invariante: Positions-Kosten +
     * „ohne Zuordnung" = Projektkosten aus {@see byProject()} (keine stille
     * Lücke).
     *
     * @return array{
     *   hasBoq: bool,
     *   positions: list<array{
     *     boqItemId:int, billId:int, billName:string, referenceNo:string,
     *     shortText:string|null, isAddendum:bool, unit:string|null,
     *     unitPrice:float|null, measuredQuantity:float, revenue:float,
     *     timeMinutes:int, costTime:float, costMaterial:float, cost:float,
     *     contribution:float, calculated:float|null, calcDelta:float|null
     *   }>,
     *   unassigned: array{timeMinutes:int, costTime:float, costMaterial:float, costExpense:float, cost:float},
     *   hasCalculation: bool,
     *   calculationImported: bool
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, int $projectId): array {
        $unassigned = ['timeMinutes' => 0, 'costTime' => 0.0, 'costMaterial' => 0.0, 'costExpense' => 0.0, 'cost' => 0.0];

        $bills = BillOfQuantity::query()
            ->where('project_id', $projectId)
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($bills->isEmpty()) {
            return ['hasBoq' => false, 'positions' => [], 'unassigned' => $unassigned, 'hasCalculation' => false, 'calculationImported' => false];
        }

        $billNames = $bills->pluck('name', 'id');

        /** @var Collection<int, BoqItem> $items */
        $items = BoqItem::query()
            ->whereIn('bill_of_quantity_id', $bills->pluck('id')->all())
            ->orderBy('bill_of_quantity_id')
            ->orderBy('position')
            ->get(['id', 'bill_of_quantity_id', 'reference_no', 'short_text', 'type', 'unit', 'unit_price', 'is_addendum', 'position']);

        $itemIds = $items->pluck('id')->map(static fn($v): int => (int) $v)->all();

        // Kalkulierte Kosten je Mengeneinheit aus den GAEB-Kalkulationsdaten
        // (X52, Feature 109) - der Plan-Wert des Plan-Ist-Vergleichs. Die
        // Herkunft reist mit: Eine importierte Fremdkalkulation ist die
        // Rechnung eines anderen Betriebs, nicht die eigene Planung.
        $calculationService = app(BoqCalculationDataService::class);
        $unitCalcCosts = [];
        $calculationImported = false;
        foreach ($bills as $bill) {
            $billItemIds = array_values($items->where('bill_of_quantity_id', $bill->id)->pluck('id')->map(static fn ($v): int => (int) $v)->all());
            $unitCalcCosts += $calculationService->unitCostsFor($bill, $billItemIds);
            $calculationImported = $calculationImported || $calculationService->calculationIsImported($bill);
        }

        // Aufmaß im Zeitraum (Erlös-Basis) je Position.
        $measured = BoqItemProgress::query()
            ->whereIn('boq_item_id', $itemIds)
            ->whereBetween('captured_at', [$from, $to])
            ->selectRaw('boq_item_id, SUM(quantity) AS measured')
            ->groupBy('boq_item_id')
            ->pluck('measured', 'boq_item_id');

        // Strukturelle Zuordnungs-Verknüpfungen (bewusst NICHT periodengefiltert; Zeitraum via Quellposten eingegrenzt).
        $diaryToItems = [];
        $usageToItems = [];
        BoqItemProgress::query()
            ->whereIn('boq_item_id', $itemIds)
            ->where(static function ($q): void {
                $q->whereNotNull('diary_entry_id')->orWhereNotNull('material_usage_id');
            })
            ->get(['boq_item_id', 'diary_entry_id', 'material_usage_id'])
            ->each(static function (BoqItemProgress $link) use (&$diaryToItems, &$usageToItems): void {
                if ($link->diary_entry_id !== null) {
                    $diaryToItems[(int) $link->diary_entry_id][(int) $link->boq_item_id] = true;
                }
                if ($link->material_usage_id !== null) {
                    $usageToItems[(int) $link->material_usage_id][(int) $link->boq_item_id] = true;
                }
            });

        $materialToItems = [];
        BoqItemMapping::query()
            ->whereIn('boq_item_id', $itemIds)
            ->where('mappable_type', MorphMap::alias(Material::class))
            ->get(['boq_item_id', 'mappable_id'])
            ->each(static function (BoqItemMapping $mapping) use (&$materialToItems): void {
                $materialToItems[(int) $mapping->mappable_id][(int) $mapping->boq_item_id] = true;
            });

        // Kosten-Sammler je Position.
        $timeMinutes = array_fill_keys($itemIds, 0);
        $costTime = array_fill_keys($itemIds, 0.0);
        $costMaterial = array_fill_keys($itemIds, 0.0);

        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        /** @var Collection<int, TimeEntry> $entries */
        $entries = TimeEntry::query()
            ->where('project_id', $projectId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->get(['minutes', 'internal_rate', 'diary_entry_id']);

        foreach ($entries as $entry) {
            $itemId = $entry->diary_entry_id !== null
                ? $this->uniqueTarget($diaryToItems[(int) $entry->diary_entry_id] ?? [])
                : null;
            if ($itemId !== null) {
                $timeMinutes[$itemId] += (int) $entry->minutes;
                $costTime[$itemId] += ($entry->internal_rate?->toFloat() ?? 0.0);
            } else {
                $unassigned['timeMinutes'] += (int) $entry->minutes;
                $unassigned['costTime'] += ($entry->internal_rate?->toFloat() ?? 0.0);
            }
        }

        $timesheetIds = Timesheet::query()
            ->where('project_id', $projectId)
            ->whereBetween('work_date', [$fromDate, $toDate])
            ->pluck('id')
            ->all();

        if ($timesheetIds !== []) {
            /** @var Collection<int, MaterialUsage> $usages */
            $usages = MaterialUsage::query()
                ->whereIn('timesheet_id', $timesheetIds)
                ->get(['id', 'material_id', 'line_total_net']);

            foreach ($usages as $usage) {
                $itemId = $this->uniqueTarget($usageToItems[(int) $usage->id] ?? [])
                    ?? ($usage->material_id !== null ? $this->uniqueTarget($materialToItems[(int) $usage->material_id] ?? []) : null);
                if ($itemId !== null) {
                    $costMaterial[$itemId] += $usage->line_total_net?->toFloat() ?? 0.0;
                } else {
                    $unassigned['costMaterial'] += $usage->line_total_net?->toFloat() ?? 0.0;
                }
            }
        }

        // Spesen tragen keinen LV-Anker → vollständig „ohne Zuordnung" (wie expenseAggregate je Projekt).
        $unassigned['costExpense'] = $this->economics->expenseAggregate($fromDate, $toDate, projectId: $projectId)['cost'];

        $unassigned['costTime'] = round($unassigned['costTime'], 2);
        $unassigned['costMaterial'] = round($unassigned['costMaterial'], 2);
        $unassigned['cost'] = round($unassigned['costTime'] + $unassigned['costMaterial'] + $unassigned['costExpense'], 2);

        $positions = [];
        foreach ($items as $item) {
            $id = (int) $item->id;
            $quantity = (float) ($measured[$id] ?? 0.0);
            $unitPrice = $item->unit_price?->toFloat();
            $revenue = $item->type->isBillable() && $unitPrice !== null
                ? DocumentTotalsCalculator::lineNet($quantity, $unitPrice)->withScale(2)->toFloat()
                : 0.0;
            $cost = round($costTime[$id] + $costMaterial[$id], 2);

            // Nur Positionen mit Bewegung im Zeitraum — ein LV kann hunderte
            // unberührte Positionen tragen, die den Report nur verwässern.
            if ($quantity === 0.0 && $cost === 0.0 && $timeMinutes[$id] === 0) {
                continue;
            }

            // Kalkuliert wurde die volle LV-Menge; verglichen wird mit dem,
            // was bisher ausgeführt ist - sonst sähe jeder unfertige Abschnitt
            // wie eine Ersparnis aus.
            $calculated = isset($unitCalcCosts[$id]) ? round($unitCalcCosts[$id] * $quantity, 2) : null;

            $positions[] = [
                'boqItemId' => $id,
                'billId' => (int) $item->bill_of_quantity_id,
                'billName' => (string) ($billNames[$item->bill_of_quantity_id] ?? '—'),
                'referenceNo' => (string) $item->reference_no,
                'shortText' => $item->short_text,
                'isAddendum' => (bool) $item->is_addendum,
                'unit' => $item->unit,
                'unitPrice' => $unitPrice,
                'measuredQuantity' => round($quantity, 4),
                'revenue' => $revenue,
                'timeMinutes' => $timeMinutes[$id],
                'costTime' => round($costTime[$id], 2),
                'costMaterial' => round($costMaterial[$id], 2),
                'cost' => $cost,
                'contribution' => round($revenue - $cost, 2),
                'calculated' => $calculated,
                // Ohne Kalkulation gibt es nichts zu vergleichen - null, nicht 0 €.
                'calcDelta' => $calculated === null ? null : round($cost - $calculated, 2),
            ];
        }

        return [
            'hasBoq' => true,
            'positions' => $positions,
            'unassigned' => $unassigned,
            'hasCalculation' => $unitCalcCosts !== [],
            'calculationImported' => $calculationImported,
        ];
    }

    /**
     * Genau EIN Zuordnungsziel → dessen ID, sonst null (mehrdeutige
     * Verknüpfungen werden nicht still aufgeteilt).
     *
     * @param  array<int, true>  $targets
     */
    private function uniqueTarget(array $targets): ?int {
        return count($targets) === 1 ? array_key_first($targets) : null;
    }
}
