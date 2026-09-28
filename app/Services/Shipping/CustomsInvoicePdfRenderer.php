<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomsInvoicePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Shipping\ShipmentExportReason;
use App\Models\Article\Article;
use App\Models\Customer\Customer;
use App\Models\Inventory\StockDelivery;
use App\Models\Invoicing\InvoiceItem;
use App\Models\Platform\Organization;
use App\Models\Shipping\ShipmentParcel;
use App\Services\Billing\DocumentTotalsCalculator;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Settings\SettingsRegistry;
use App\Support\DocumentLocale;
use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Validation\ValidationException;

/**
 * Zollpapier zu einer Auslieferung (Feature 059, MVP-1007): Handelsrechnung
 * beim Verkauf, sonst Proformarechnung. Ein Dokument, keine Zollanmeldung.
 */
class CustomsInvoicePdfRenderer {
    public function __construct(private readonly DocumentDesignRenderer $design) {}

    /** Ziel außerhalb der EU — ohne Land gilt Deutschland wie beim Versandlabel. */
    public function requiresCustoms(StockDelivery $delivery): bool {
        $country = CountryCode::tryFrom(strtoupper(trim((string) ($delivery->customer?->country ?: 'DE'))));

        return $country === null || ! $country->isEU();
    }

    public function render(StockDelivery $delivery, ShipmentExportReason $reason): string {
        $delivery->loadMissing(['customer', 'variant.article', 'parcels', 'shipment', 'invoiceItems.invoice']);
        $customer = $delivery->customer;
        if (! $customer instanceof Customer) {
            throw ValidationException::withMessages(['customs' => (string) __('shipping.customs.error.no_customer')]);
        }
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($delivery->organization_id);
        $position = $this->position($delivery);
        $seller = app(XRechnungGenerator::class)->sellerDataFor($organization);
        $commercial = $reason === ShipmentExportReason::Sale;
        $grossGrams = $delivery->parcels->sum(static fn (ShipmentParcel $parcel): int => (int) $parcel->weight_grams);
        $invoice = $delivery->invoiceItems->sortByDesc('id')->map(static fn (InvoiceItem $item) => $item->invoice)->filter()->first();

        return DocumentLocale::within($customer, $organization, fn (): string => $this->design->renderPdf(
            RenderDocumentKind::DeliveryNote,
            'pdf.customs-invoice',
            [
                'title' => (string) __($commercial ? 'shipping.customs.commercial_invoice' : 'shipping.customs.proforma_invoice'),
                'number' => $this->number($delivery, $reason),
                'date' => now()->format('d.m.Y'),
                'deliveryNoteNumber' => 'LS-' . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT),
                'invoiceNumber' => $commercial ? $invoice?->number : null,
                'tracking' => $delivery->shipment?->tracking_number,
                'reason' => $reason,
                'commercial' => $commercial,
                'sender' => [
                    'name' => $seller['name'],
                    'lines' => array_values(array_filter([$seller['street'], trim($seller['zip'] . ' ' . $seller['city']), $seller['country']])),
                    'vat_id' => $seller['vat_id'],
                    'eori' => strtoupper(trim((string) app(SettingsRegistry::class)->effective('shipping.eori_number', $organization)->value)),
                ],
                'recipient' => [
                    'name' => (string) $customer->displayLabel(),
                    'lines' => array_values(array_filter([(string) $customer->address_street, trim($customer->address_zip . ' ' . $customer->address_city), strtoupper((string) $customer->country)])),
                    'vat_id' => trim((string) $customer->vat_id),
                ],
                'position' => $position,
                'grossWeight' => $grossGrams > 0 ? Decimal::of((string) $grossGrams, 0)->dividedBy(Decimal::of('1000', 0), 3)->format() : null,
                'parcels' => $delivery->parcels->count(),
            ],
            $organization,
        ));
    }

    public function number(StockDelivery $delivery, ShipmentExportReason $reason): string {
        return ($reason === ShipmentExportReason::Sale ? 'HR-' : 'PF-') . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Fehlende Zollangaben der Position — der Zoll weist unvollständige Papiere
     * zurück, deshalb entsteht ohne sie kein Dokument.
     *
     * @return list<string>
     */
    public function missingFields(StockDelivery $delivery): array {
        $delivery->loadMissing('variant.article');
        $article = $delivery->variant?->article;
        $missing = [];
        if (trim((string) $article?->customs_tariff_number) === '') {
            $missing[] = (string) __('article.field.customs_tariff_number');
        }
        if (trim((string) $article?->origin_country) === '') {
            $missing[] = (string) __('article.field.origin_country');
        }
        if ($article?->net_weight_kg === null) {
            $missing[] = (string) __('article.field.net_weight_kg');
        }
        if ($this->unitPrice($delivery) === null) {
            $missing[] = (string) __('shipping.customs.value');
        }

        return $missing;
    }

    private function unitPrice(StockDelivery $delivery): ?Money {
        return $delivery->unit_price_snapshot ?? $delivery->variant->sale_price ?? $delivery->variant->article->default_sale_price ?? null;
    }

    /** @return array{description: string, sku: ?string, tariff: string, origin: string, quantity: string, unit: string, net_weight: string, unit_value: string, total_value: string, currency: string} */
    private function position(StockDelivery $delivery): array {
        $missing = $this->missingFields($delivery);
        $article = $delivery->variant?->article;
        $unitPrice = $this->unitPrice($delivery);
        if ($missing !== [] || ! $article instanceof Article || $article->net_weight_kg === null || $unitPrice === null) {
            throw ValidationException::withMessages(['customs' => (string) __('shipping.customs.error.missing', [
                'article' => $delivery->name_snapshot,
                'fields' => implode(', ', $missing),
            ])]);
        }

        $quantity = $delivery->quantity?->getValue() ?? Decimal::of('0', 0);
        $scale = $unitPrice->getCurrency()->getDefaultFractionDigits();
        $total = DocumentTotalsCalculator::lineNet($quantity->getValue(), $unitPrice, currency: $unitPrice->getCurrency())->withScale($scale);

        return [
            'description' => (string) $delivery->name_snapshot,
            'sku' => $delivery->sku_snapshot,
            'tariff' => (string) $article->customs_tariff_number,
            'origin' => strtoupper((string) $article->origin_country),
            'quantity' => NumberHelper::trimTrailingZeros($quantity->format(), ','),
            'unit' => (string) $delivery->unit,
            'net_weight' => $article->net_weight_kg->times($quantity, 3)->format(),
            // Preise mit vier Stellen nur dann so zeigen, wenn sie sie brauchen.
            'unit_value' => ($unitPrice->compareTo($unitPrice->withScale($scale)) === 0 ? $unitPrice->withScale($scale) : $unitPrice)->format(),
            'total_value' => $total->format(),
            'currency' => $unitPrice->getCurrency()->value,
        ];
    }
}
