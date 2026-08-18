<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostEstimateService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Models\{BillOfQuantity, Project, User};
use App\Models\Catalog\CatalogRegistry;
use App\Models\Costing\{CostEstimate, CostEstimateItem};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Entities\Gaeb\{GaebBoq, GaebCostElement, GaebCosting};
use ERechnungToolkit\Enums\{GaebCostingType, GaebPhase};
use ERechnungToolkit\Generators\GaebDaXmlGenerator;
use ERechnungToolkit\Parsers\GaebCostingParser;
use Illuminate\Support\Facades\DB;

/**
 * Kostenermittlungen lesen und schreiben (Feature 109, MVP-646).
 *
 * **Gelesen werden alle vier HOAI-Stufen, erzeugt nur zwei.** Kostenschätzung
 * und Kostenberechnung stammen aus der Planung — dafür hält WorkDiary keine
 * Daten, und sie zu erfinden hieße, eine Kennwertdatenbank vorzutäuschen (D5).
 * Der **Kostenanschlag** dagegen ergibt sich aus dem Vergabe- und
 * Auftragsstand, die **Kostenfeststellung** aus dem aufgemessenen Stand —
 * beides liegt vor.
 *
 * **Die Ausgabe reist mit.** Eine Kostengruppe ohne ihre DIN-276-Ausgabe ist
 * mehrdeutig; die Ermittlung merkt sich deshalb den Katalogstamm, gegen den
 * ihre Nummern zu lesen sind.
 */
final class CostEstimateService {
    public function __construct(
        private readonly CostGroupReportService $report,
        private readonly CatalogAssignmentService $assignments,
    ) {}

    /**
     * Übernimmt eine fremde X51 als Kostenermittlung — der Weg, auf dem das
     * Budget in die Kostenverfolgung kommt.
     */
    public function import(string $xml, int $organizationId, User $actor, ?Project $project = null): CostEstimate {
        $costing = (new GaebCostingParser)->parse($xml);

        return DB::transaction(function () use ($costing, $organizationId, $actor, $project): CostEstimate {
            $estimate = CostEstimate::query()->create([
                'organization_id' => $organizationId,
                'project_id' => $project?->id,
                'name' => mb_substr($costing->getLabel() ?? $costing->getName(), 0, 200),
                'stage' => $this->stageOf($costing->getType()),
                'method' => $costing->getMethod()?->value,
                // Ohne Datum in der Datei zählt der Tag der Übernahme: Ein
                // erfundenes Datum wäre schlechter als ein ehrliches.
                'determined_on' => $costing->getDate() ?? now()->toDateString(),
                'currency' => 'EUR',
                'source' => CostEstimate::SOURCE_IMPORT,
                'catalog_registry_id' => $this->registryOf($costing)?->id,
                'created_by' => $actor->id,
            ]);

            $position = 0;
            foreach ($costing->getElements() as $element) {
                $this->storeElement($estimate, $element, null, 1, $position);
            }

            return $estimate;
        });
    }

    /**
     * Erzeugt eine Kostenermittlung aus dem Stand eines Leistungsverzeichnisses.
     *
     * Der **Kostenanschlag** nimmt den LV-Ansatz samt Nachträgen — das ist der
     * Stand, zu dem vergeben wurde. Die **Kostenfeststellung** nimmt die
     * aufgemessene Leistung. Beide gehen über die Kostengruppen-Auswertung,
     * damit dieselbe Verteilungsregel gilt wie am Bildschirm.
     */
    public function deriveFromBill(BillOfQuantity $bill, string $stage, User $actor): CostEstimate {
        $lifecycle = $this->report->lifecycle($bill, null, 3);
        $catalog = $this->assignments->costGroupCatalog($bill);
        $registry = $catalog === null ? null : $this->assignments->registryFor($catalog);

        return DB::transaction(function () use ($bill, $stage, $actor, $lifecycle, $registry): CostEstimate {
            $estimate = CostEstimate::query()->create([
                'organization_id' => $bill->organization_id,
                'project_id' => $bill->project_id,
                'bill_of_quantity_id' => $bill->id,
                'name' => mb_substr($bill->name, 0, 200),
                'stage' => in_array($stage, CostEstimate::STAGES, true) ? $stage : CostEstimate::STAGE_QUOTE,
                'method' => 'cost by elements',
                'determined_on' => now()->toDateString(),
                'currency' => $bill->currency->value,
                'source' => CostEstimate::SOURCE_DERIVED,
                'catalog_registry_id' => $registry?->id,
                'created_by' => $actor->id,
            ]);

            $position = 0;
            foreach ($lifecycle['rows'] as $row) {
                // Die Kostenfeststellung nimmt, was aufgemessen ist; der
                // Anschlag, was vergeben wurde.
                $amount = $stage === CostEstimate::STAGE_FINAL
                    ? $row['executed']
                    : $row['boq'] + $row['addenda'];
                if ($amount === 0.0) {
                    continue;
                }

                CostEstimateItem::query()->create([
                    'cost_estimate_id' => $estimate->id,
                    'code' => $row['code'] !== '' ? $row['code'] : null,
                    'label' => mb_substr($row['label'], 0, 300),
                    'unit' => 'psch',
                    'amount' => round($amount, 2),
                    'level' => 1,
                    'position' => $position++,
                ]);
            }

            return $estimate;
        });
    }

    /**
     * Schreibt die Ermittlung als GAEB **X51**.
     *
     * Erzeugt wird nur, was WorkDiary belegen kann — Kostenanschlag und
     * Kostenfeststellung (D5). Für Schätzung und Berechnung fehlt die
     * Datengrundlage; sie werden gelesen, nicht geschrieben.
     */
    public function export(CostEstimate $estimate): string {
        $elements = [];
        foreach ($estimate->items as $item) {
            $elements[] = new GaebCostElement(
                description: $item->label,
                unit: $item->unit ?? 'psch',
                number: $item->code,
                total: $item->amount === null ? null : Money::of((string) $item->amount, CurrencyCode::from($estimate->currency)),
            );
        }

        $costing = new GaebCosting(
            name: mb_substr($estimate->name, 0, 20),
            elements: $elements,
            label: $estimate->name,
            type: $this->costingTypeOf($estimate->stage),
            date: $estimate->determined_on->toDateString(),
        );

        // Die Kostenermittlung hat kein Leistungsverzeichnis — sie gliedert
        // nach Kostengruppen, nicht nach Gewerken. Der Generator verlangt den
        // Parameter trotzdem; dieselbe Konvention nutzt der Rechnungsexport.
        return (new GaebDaXmlGenerator)->generate(
            new GaebBoq(currency: CurrencyCode::from($estimate->currency)),
            GaebPhase::CostEstimate,
            $estimate->currency,
            $estimate->determined_on->toDateString(),
            costing: $costing,
        );
    }

    /**
     * Legt ein Kostenelement samt Kindern ab — die Hierarchie bleibt über
     * `parent_code` erhalten.
     */
    private function storeElement(CostEstimate $estimate, GaebCostElement $element, ?string $parentCode, int $level, int &$position): void {
        CostEstimateItem::query()->create([
            'cost_estimate_id' => $estimate->id,
            'code' => $element->getNumber(),
            'label' => mb_substr($element->getDescription(), 0, 300),
            'quantity' => $element->getQuantity(),
            'unit' => $element->getUnit(),
            'unit_price' => $element->getUnitPrice()?->toFloat(),
            'amount' => $element->getTotal()?->toFloat(),
            'level' => $level,
            'parent_code' => $parentCode,
            'position' => $position++,
        ]);

        foreach ($element->getChildren() as $child) {
            $this->storeElement($estimate, $child, $element->getNumber(), $level + 1, $position);
        }
    }

    private function stageOf(?GaebCostingType $type): string {
        return match ($type) {
            GaebCostingType::Estimate => CostEstimate::STAGE_ESTIMATE,
            GaebCostingType::Calculation => CostEstimate::STAGE_CALCULATION,
            GaebCostingType::FinalStatement => CostEstimate::STAGE_FINAL,
            // „cost planning" ist der Kostenanschlag; ohne Angabe ist er die
            // wahrscheinlichste Stufe für eine ausgetauschte Datei.
            default => CostEstimate::STAGE_QUOTE,
        };
    }

    private function costingTypeOf(string $stage): GaebCostingType {
        return match ($stage) {
            CostEstimate::STAGE_ESTIMATE => GaebCostingType::Estimate,
            CostEstimate::STAGE_CALCULATION => GaebCostingType::Calculation,
            CostEstimate::STAGE_FINAL => GaebCostingType::FinalStatement,
            default => GaebCostingType::Quote,
        };
    }

    /**
     * Der Katalogstamm, gegen den die Nummern der Ermittlung zu lesen sind.
     * Ohne Katalogangabe in der Datei bleibt er offen — raten hieße, „310"
     * einer Ausgabe zuzuschlagen, die vielleicht gar nicht gemeint war.
     */
    private function registryOf(GaebCosting $costing): ?CatalogRegistry {
        foreach ($costing->getElements() as $element) {
            foreach ($element->getCatalogAssignments() as $assignment) {
                $registry = CatalogRegistry::query()
                    ->where('gaeb_type', $assignment->getCatalogId())
                    ->where('active', true)
                    ->first();
                if ($registry !== null) {
                    return $registry;
                }
            }
        }

        return null;
    }
}
