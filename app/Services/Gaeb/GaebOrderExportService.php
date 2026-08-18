<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebOrderExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Models\{PurchaseOrder, PurchaseOrderLine};
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Entities\Gaeb\{GaebBoq, GaebOrder, GaebOrderItem, GaebParty};
use ERechnungToolkit\Enums\GaebPhase;
use ERechnungToolkit\Generators\GaebDaXmlGenerator;

/**
 * Bestellungen als GAEB-Handelsdatei ausgeben (X93/X96).
 *
 * Baustoffhändler tauschen Bestellungen über den GAEB-Handelsweg aus — neben
 * OCI-Punchout und openTRANS die dritte Strecke, und die verbreitetste im Bau.
 * Anders als ein Leistungsverzeichnis identifiziert sie über die
 * **Artikelnummer** des Lieferanten, und der Liefertermin hängt an der Zeile.
 */
final class GaebOrderExportService {
    public function __construct(
        private readonly XRechnungGenerator $einvoice,
        private readonly GaebDaXmlGenerator $generator = new GaebDaXmlGenerator,
    ) {}

    /**
     * @return array{content: string, filename: string, losses: list<string>}
     */
    public function export(PurchaseOrder $order, GaebPhase $phase = GaebPhase::Order): array {
        // `currency` ist bereits als Enum gecastet - app-weit der einzige
        // Währungstyp.
        $currency = $order->currency ?? CurrencyCode::Euro;

        $content = $this->generator->generate(
            new GaebBoq,
            $phase,
            $currency->value,
            $order->ordered_at?->format('Y-m-d'),
            order: $this->document($order, $phase, $currency),
        );

        return [
            'content' => $content,
            'filename' => sprintf('Bestellung-%s.x%s', $order->number ?? $order->id, $phase->value),
            'losses' => $this->losses($order),
        ];
    }

    private function document(PurchaseOrder $order, GaebPhase $phase, CurrencyCode $currency): GaebOrder {
        $supplier = $order->supplier;

        return new GaebOrder(
            // Ohne Liefertermin ist eine Bestellung unbestimmt; fehlt er, gilt
            // das Bestelldatum als Platzhalter, was der Preflight meldet.
            deliveryDate: $order->expected_at?->format('Y-m-d')
                ?? $order->ordered_at?->format('Y-m-d')
                ?? now()->format('Y-m-d'),
            items: array_values(array_map(
                fn (PurchaseOrderLine $line): GaebOrderItem => $this->line($line, $order, $currency),
                $order->lines->all()
            )),
            supplier: $this->party($supplier?->name, $supplier?->address_street, $supplier?->address_zip, $supplier?->address_city),
            supplierTaxNo: $supplier?->tax_number ?: $supplier?->vat_id,
            // Eine Handelsregisternummer führt das Lieferantenstammblatt nicht;
            // Einzelunternehmer haben ohnehin keine. Das Element bleibt leer,
            // was das Schema zulässt.
            supplierRegisterNo: null,
            // Die eigene Anschrift kommt aus den E-Rechnungs-Stammdaten -
            // eine zweite Absenderpflege wäre eine Fehlerquelle.
            customer: $this->ownParty($order),
            orderConfirmationNo: $phase === GaebPhase::Order ? $order->number : null,
            inquiryNo: $phase === GaebPhase::PriceInquiry ? $order->number : null,
        );
    }

    private function line(PurchaseOrderLine $line, PurchaseOrder $order, CurrencyCode $currency): GaebOrderItem {
        $price = $line->unit_price;

        return new GaebOrderItem(
            // Der Händler findet die Ware über seine eigene Nummer; ohne sie
            // bliebe nur der Text, und den liest keine Warenwirtschaft.
            catalogArticleNo: (string) ($line->supplier_sku ?? $line->article_id ?? '—'),
            description: $line->description,
            quantity: $line->ordered_qty === null ? null : (string) $line->ordered_qty,
            unit: $line->unit,
            price: $price === null ? null : Money::of((string) $price, $currency),
            deliveryDate: $order->expected_at?->format('Y-m-d'),
            supplierArticleNo: $line->supplier_sku,
        );
    }

    /** Eigene Anschrift als Besteller. */
    private function ownParty(PurchaseOrder $order): ?GaebParty {
        $seller = $this->einvoice->sellerDataFor($order->organization);

        return $this->party($seller['name'], $seller['street'], $seller['zip'], $seller['city']);
    }

    private function party(?string $name, ?string $street, ?string $zip, ?string $city): ?GaebParty {
        if ((string) $name === '' || (string) $street === '' || (string) $zip === '' || (string) $city === '') {
            return null;
        }

        return new GaebParty((string) $name, (string) $street, (string) $zip, (string) $city);
    }

    /**
     * Was die Gegenseite beanstanden wird. Kein Abbruch — die Datei entsteht,
     * aber der Besteller weiß, woran sie scheitern kann.
     *
     * @return list<string>
     */
    private function losses(PurchaseOrder $order): array {
        $losses = [];

        if ($order->expected_at === null) {
            $losses[] = (string) __('gaeb.trade.missing_delivery_date');
        }

        $withoutSku = $order->lines->filter(static fn (PurchaseOrderLine $line): bool => (string) $line->supplier_sku === '')->count();
        if ($withoutSku > 0) {
            $losses[] = (string) __('gaeb.trade.missing_supplier_sku', ['count' => $withoutSku]);
        }

        if ((string) $order->supplier?->tax_number === '' && (string) $order->supplier?->vat_id === '') {
            $losses[] = (string) __('gaeb.trade.missing_supplier_tax_no');
        }

        // Ohne eigene Anschrift bleibt der Kundenblock leer, und das Schema
        // verlangt dort mindestens eine Adresse - die Datei wäre unbrauchbar.
        if ($this->ownParty($order) === null) {
            $losses[] = (string) __('gaeb.trade.missing_own_address');
        }

        return $losses;
    }
}
