<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqDocumentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\GaebPhase;
use App\Models\{BillOfQuantity, BoqItem, BoqSection};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Entities\Gaeb\{GaebBoq, GaebItem, GaebSection, GaebSubDescription, GaebTextComplement, GaebTotals, GaebUpComponent};
use ERechnungToolkit\Enums\{GaebAlternativeBidStatus, GaebChangeOrderStatus, GaebItemType};

/**
 * Übersetzt ein gespeichertes Leistungsverzeichnis in das formatneutrale
 * Dokument des erechnung-toolkits (Feature 108). Die Formatlogik selbst liegt
 * im Toolkit — hier steht nur die Zuordnung der eigenen Datenhaltung.
 */
class BoqDocumentFactory {
    /** Beträge mit der Skala der Modelle: GAEB-Einheitspreise tragen 1/10 Cent. */
    private const PRICE_SCALE = 4;

    public function fromModel(BillOfQuantity $boq, GaebPhase $phase): GaebBoq {
        $boq->loadMissing(['sections', 'items']);

        $sections = [];
        foreach ($boq->sections->sortBy('position') as $section) {
            $sections[] = $this->section($section, $boq);
        }

        $items = [];
        foreach ($boq->items->sortBy('position') as $item) {
            $items[] = $this->item($item, $boq);
        }

        $components = [];
        foreach ($boq->up_components ?? [] as $component) {
            $components[] = new GaebUpComponent(
                no: (int) $component['no'],
                label: $component['label'] ?? null,
                category: $component['category'] ?? null,
            );
        }

        return new GaebBoq(
            version: $boq->gaeb_version ?? '3.3',
            phaseCode: $phase->value,
            projectName: $boq->name,
            externalId: $boq->external_id,
            sections: $sections,
            items: $items,
            upComponents: $components,
            totals: $this->totals($boq->totals, $boq->currency),
            currency: $boq->currency,
        );
    }

    private function section(BoqSection $section, BillOfQuantity $boq): GaebSection {
        $parent = $section->parent_id !== null
            ? $boq->sections->firstWhere('id', $section->parent_id)
            : null;

        return new GaebSection(
            reference: $section->reference_no,
            parentReference: $parent?->reference_no,
            label: $section->label,
            position: $section->position,
            totals: $this->totals($section->totals, $boq->currency),
            externalId: $section->external_id,
        );
    }

    private function item(BoqItem $item, BillOfQuantity $boq): GaebItem {
        $section = $item->boq_section_id !== null
            ? $boq->sections->firstWhere('id', $item->boq_section_id)
            : null;

        $complements = [];
        foreach ($item->text_complements ?? [] as $complement) {
            $complements[] = new GaebTextComplement(
                mark: (string) $complement['mark'],
                kind: $complement['kind'] ?? null,
                caption: $complement['caption'] ?? null,
                body: $complement['body'] ?? null,
                tail: $complement['tail'] ?? null,
            );
        }

        $subDescriptions = [];
        foreach ($item->sub_descriptions ?? [] as $sub) {
            $subDescriptions[] = new GaebSubDescription(
                no: $sub['no'] ?? null,
                quantity: $sub['quantity'] ?? null,
                unit: $sub['unit'] ?? null,
            );
        }

        return new GaebItem(
            reference: $item->reference_no,
            sectionReference: $section?->reference_no,
            type: GaebItemType::from($item->type->value),
            shortText: $item->short_text,
            longText: $item->long_text,
            quantity: $item->quantity?->getNumericValue(),
            unit: $item->unit,
            unitPrice: $item->unit_price,
            totalPrice: $item->total_price,
            provisionKind: $item->provision_kind,
            alternativeGroup: $item->alternative_group,
            alternativeNo: $item->alternative_no,
            markupType: $item->markup_type,
            textComplements: $complements,
            subDescriptions: $subDescriptions,
            unitPriceComponents: $this->shares($item->unit_price_components, $item->currency),
            changeOrderNo: $item->change_order_no,
            changeOrderStatus: $item->change_order_status !== null
                ? GaebChangeOrderStatus::from($item->change_order_status->value)
                : null,
            notOffered: $item->not_offered,
            notApplicable: $item->not_applicable,
            quantityToBeDetermined: $item->free_quantity,
            hourlyItem: $item->hourly_item,
            discountPercent: $item->discount_percent,
            vatRate: $item->vat_rate,
            bidderComment: $item->bidder_comment,
            alternativeBidStatus: $item->alternative_bid_status !== null
                ? GaebAlternativeBidStatus::from($item->alternative_bid_status)
                : null,
            externalId: $item->external_id,
            position: $item->position,
        );
    }

    /** @param array<string, string|null>|null $totals */
    private function totals(?array $totals, CurrencyCode $currency): ?GaebTotals {
        if ($totals === null) {
            return null;
        }

        return new GaebTotals(
            total: $this->money($totals['total'] ?? null, $currency),
            discountPercent: $totals['discount_percent'] ?? null,
            discountAmount: $this->money($totals['discount_amount'] ?? null, $currency),
            totalAfterDiscount: $this->money($totals['total_after_discount'] ?? null, $currency),
            vatRate: $totals['vat_rate'] ?? null,
            totalNet: $this->money($totals['total_net'] ?? null, $currency),
            vatAmount: $this->money($totals['vat_amount'] ?? null, $currency),
            totalGross: $this->money($totals['total_gross'] ?? null, $currency),
        );
    }

    /** Betrag aus dem gespeicherten Summen-JSON; Beträge sind dort Dezimalstrings. */
    private function money(mixed $value, CurrencyCode $currency): ?Money {
        return is_string($value) || is_int($value)
            ? Money::ofNullable((string) $value, $currency, self::PRICE_SCALE)
            : null;
    }

    /**
     * Einheitspreisanteile: im Modell Dezimalstrings, im Toolkit Money.
     *
     * @param  array<int|string, mixed>|null  $components
     * @return list<Money>
     */
    private function shares(?array $components, CurrencyCode $currency): array {
        $shares = [];
        foreach (array_values($components ?? []) as $value) {
            $share = $this->money($value, $currency);
            if ($share !== null) {
                $shares[] = $share;
            }
        }

        return $shares;
    }
}
