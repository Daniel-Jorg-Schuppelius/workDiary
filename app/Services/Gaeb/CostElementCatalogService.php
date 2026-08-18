<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostElementCatalogService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Models\Costing\{CostElement, CostElementCatalog};
use App\Models\User;
use ERechnungToolkit\Entities\Gaeb\{GaebCostElement, GaebCosting};
use ERechnungToolkit\Enums\GaebPhase;
use ERechnungToolkit\Generators\GaebDaXmlGenerator;
use ERechnungToolkit\Parsers\GaebCostingParser;
use Illuminate\Support\Facades\DB;

/**
 * Baukostenkataloge lesen und schreiben (Feature 109, MVP-645).
 *
 * Ein Baukostenkatalog ist ein **Nachschlagewerk**: Er sagt, was ein Bauteil
 * üblicherweise kostet, nicht was ein bestimmtes Vorhaben kostet. Damit
 * speist er die frühen HOAI-Stufen, für die WorkDiary sonst keine Zahlen hat.
 *
 * Zwei Eigenheiten des Formats bestimmen den Ablauf:
 *
 * - **Der Kennwert ist eine Spanne.** Von, Mittel und bis reisen zusammen;
 *   nur den Mittelwert zu übernehmen verschwiege, wie sicher er ist.
 * - **Zwei Bauformen, dieselbe Struktur.** X50.2 nummeriert vollständig
 *   (`EleNo`), X50.1 in Teilen (`ElePart`). Welche vorlag, merkt sich der
 *   Katalog — beim Schreiben muss dieselbe gewählt werden, sonst liest die
 *   Gegenseite andere Nummern.
 */
final class CostElementCatalogService {
    /** Übernimmt eine X50 als Katalog. */
    public function import(string $xml, int $organizationId, User $actor, ?string $name = null): CostElementCatalog {
        $costing = (new GaebCostingParser)->parse($xml);

        return DB::transaction(function () use ($costing, $organizationId, $actor, $name): CostElementCatalog {
            $catalog = CostElementCatalog::query()->create([
                'organization_id' => $organizationId,
                'name' => mb_substr($name ?? $costing->getLabel() ?? $costing->getName(), 0, 200),
                'valid_on' => $costing->getDate(),
                'currency' => 'EUR',
                'full_element_numbers' => $costing->hasFullElementNumbers(),
                'source' => CostElementCatalog::SOURCE_IMPORT,
                'created_by' => $actor->id,
            ]);

            $position = 0;
            foreach ($costing->getElements() as $element) {
                $this->store($catalog, $element, null, 1, $position);
            }

            return $catalog;
        });
    }

    /**
     * Schreibt den Katalog als GAEB **X50** — in der Bauform, in der er
     * hereinkam.
     */
    public function export(CostElementCatalog $catalog): string {
        $elements = [];
        foreach ($catalog->elements()->whereNull('parent_code')->get() as $root) {
            $elements[] = $this->toEntity($catalog, $root);
        }

        $costing = new GaebCosting(
            name: mb_substr($catalog->name, 0, 20),
            elements: $elements,
            label: $catalog->name,
            date: $catalog->valid_on?->toDateString(),
            fullElementNumbers: $catalog->full_element_numbers,
        );

        // Der Katalog hat kein Leistungsverzeichnis — dieselbe Konvention wie
        // bei Rechnung und Kostenermittlung.
        return (new GaebDaXmlGenerator)->generate(
            new \ERechnungToolkit\Entities\Gaeb\GaebBoq(currency: \CommonToolkit\Enums\CurrencyCode::from($catalog->currency)),
            GaebPhase::CostCatalogue,
            $catalog->currency,
            $catalog->valid_on?->toDateString(),
            costing: $costing,
        );
    }

    /**
     * Legt ein Element samt Kindern ab; die Hierarchie hängt an
     * `parent_code`.
     */
    private function store(CostElementCatalog $catalog, GaebCostElement $element, ?string $parentCode, int $level, int &$position): void {
        CostElement::query()->create([
            'cost_element_catalog_id' => $catalog->id,
            'code' => $element->getNumber(),
            'label' => mb_substr($element->getDescription(), 0, 300),
            'unit' => $element->getUnit(),
            // Die Spanne bleibt vollständig — ein früher Kennwert ist eine
            // Schätzung, und das gehört sichtbar.
            'unit_price_from' => $element->getUnitPriceFrom()?->toFloat(),
            'unit_price_avg' => $element->getUnitPriceAverage()?->toFloat() ?? $element->getUnitPrice()?->toFloat(),
            'unit_price_to' => $element->getUnitPriceTo()?->toFloat(),
            'remark' => $element->getRemark() === null ? null : mb_substr($element->getRemark(), 0, 1000),
            'level' => $level,
            'parent_code' => $parentCode,
            'position' => $position++,
        ]);

        foreach ($element->getChildren() as $child) {
            $this->store($catalog, $child, $element->getNumber(), $level + 1, $position);
        }
    }

    /** Baut ein Element samt seiner Kinder für den Export zurück. */
    private function toEntity(CostElementCatalog $catalog, CostElement $element): GaebCostElement {
        $children = [];
        if ($element->code !== null) {
            foreach ($catalog->elements()->where('parent_code', $element->code)->get() as $child) {
                $children[] = $this->toEntity($catalog, $child);
            }
        }

        $money = fn (?string $value): ?\CommonToolkit\ValueObjects\Money => $value === null
            ? null
            : \CommonToolkit\ValueObjects\Money::of($value, \CommonToolkit\Enums\CurrencyCode::from($catalog->currency));

        return new GaebCostElement(
            description: $element->label,
            unit: $element->unit ?? 'psch',
            number: $element->code,
            unitPrice: $money($element->unit_price_avg),
            children: $children,
            remark: $element->remark,
            unitPriceFrom: $money($element->unit_price_from),
            unitPriceAverage: $money($element->unit_price_avg),
            unitPriceTo: $money($element->unit_price_to),
        );
    }
}
