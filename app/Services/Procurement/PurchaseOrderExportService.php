<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procurement;

use App\Models\{PurchaseOrder, PurchaseOrderLine, Supplier};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use DateTimeImmutable;
use ERechnungToolkit\Builders\OrderBuilder;
use ERechnungToolkit\Entities\{AllowanceCharge, Order, OrderLine};
use ERechnungToolkit\Enums\{OrderProfile, OrderXProfile, UnitCode};
use RuntimeException;

/**
 * Adapter zwischen einer lokalen {@see PurchaseOrder} und dem Bestell-Zweig des
 * `php-erechnung-toolkit`: erzeugt eine normkonforme elektronische Bestellung —
 * UBL (XBestellung / Peppol BIS Order) oder CII (Order-X).
 *
 * Rollen in der Bestellung: **Käufer** ist die eigene Organisation (Stammdaten
 * aus `organizations.settings['einvoice']`, dieselbe Quelle wie der Rechnungs-
 * Verkäufer in {@see \App\Services\Invoicing\EInvoice\XRechnungGenerator}),
 * **Verkäufer** ist der {@see Supplier}. Bestellzeilen werden über
 * Lieferanten-SKU (BT seller item), eigene Artikelnummer (buyer item) und GTIN
 * identifiziert.
 *
 * Das Toolkit prüft KEINE Geschäftsregeln — die fachliche Vollständigkeit
 * (Adressen, USt-ID) liegt beim Aufrufer bzw. den Stammdaten.
 */
class PurchaseOrderExportService {
    /** GS1 GTIN scheme identifier (ISO/IEC 6523). */
    private const GTIN_SCHEME = '0160';

    /**
     * Ob der Bestell-Zweig des Toolkits verfügbar ist (Order-Klassen vorhanden).
     */
    public function available(): bool {
        return class_exists(OrderBuilder::class);
    }

    /**
     * Erzeugt die Bestellung als UBL XBestellung (Peppol BIS Order only).
     */
    public function toXBestellung(PurchaseOrder $order): string {
        return $this->buildOrder($order, OrderProfile::XBESTELLUNG)->toUblXml();
    }

    /**
     * Erzeugt die Bestellung als Order-X CII (UN/CEFACT Cross Industry Order).
     */
    public function toOrderX(PurchaseOrder $order, OrderXProfile $profile = OrderXProfile::COMFORT): string {
        return $this->buildOrder($order, OrderProfile::XBESTELLUNG)->toOrderXXml($profile);
    }

    /**
     * Erzeugt die Bestellung als openTRANS 2.1 ORDER (deutscher BMEcat-naher
     * Standard für Geschäftsdokumente). Nutzt dieselbe Toolkit-{@see Order}.
     */
    public function toOpenTrans(PurchaseOrder $order): string {
        return $this->buildOrder($order, OrderProfile::XBESTELLUNG)->toOpenTransXml();
    }

    /**
     * Erzeugt die Bestellung als UGL 5.0 (GC-Gruppe / SHK-Handwerk↔Großhandel,
     * ASCII-Festsatzformat, Anfrageart BE = Lieferauftrag). Nutzt dieselbe
     * Toolkit-{@see Order}.
     */
    public function toUgl(PurchaseOrder $order): string {
        return $this->buildOrder($order, OrderProfile::XBESTELLUNG)->toUgl();
    }

    /**
     * Baut die Toolkit-{@see Order} aus der lokalen Bestellung.
     */
    public function buildOrder(PurchaseOrder $order, OrderProfile $profile = OrderProfile::XBESTELLUNG): Order {
        if (! $this->available()) {
            throw new RuntimeException('Der Bestell-Zweig des php-erechnung-toolkit ist nicht verfügbar.');
        }

        $order->loadMissing(['supplier', 'lines.article', 'lines.variant', 'organization', 'warehouse']);

        $supplier = $order->supplier;
        if (! $supplier instanceof Supplier) {
            throw new RuntimeException('Bestellung ohne Lieferant kann nicht exportiert werden.');
        }

        $buyer = $this->buyerData($order);
        $currency = $order->currency ?? CurrencyCode::Euro;
        $issueDate = ($order->ordered_at ?? $order->created_at ?? now())->toDateString();

        $builder = OrderBuilder::create((string) $order->number)
            ->withProfile($profile)
            ->withCurrency($currency)
            ->withIssueDate(new DateTimeImmutable($issueDate))
            // Käufer = eigene Organisation
            ->withBuyer($buyer['name'], $buyer['vat_id'] !== '' ? $buyer['vat_id'] : null)
            ->withBuyerAddress($buyer['street'], $buyer['zip'], $buyer['city'], $buyer['country'])
            // Verkäufer = Lieferant
            ->withSeller(
                (string) ($supplier->company ?: $supplier->name),
                trim((string) $supplier->vat_id) !== '' ? trim((string) $supplier->vat_id) : null,
                trim((string) $supplier->tax_number) !== '' ? trim((string) $supplier->tax_number) : null,
            )
            ->withSellerAddress(
                trim((string) $supplier->address_street),
                trim((string) $supplier->address_zip),
                trim((string) $supplier->address_city),
                strtoupper(trim((string) $supplier->country) ?: 'DE'),
            );

        if ($buyer['contact_email'] !== '') {
            $builder->withBuyerEndpoint($buyer['contact_email'], 'EM');
        }
        $supplierEmail = trim((string) $supplier->email);
        if ($supplierEmail !== '') {
            $builder->withSellerEndpoint($supplierEmail, 'EM');
        }

        // Lieferadresse (UGL ADR): empfangendes Lager als Bezeichnung/Hinweis an der
        // Org-Adresse. Wirkt nur im UGL-Export; UBL/Order-X/openTRANS ignorieren sie.
        $warehouse = $order->warehouse;
        if ($warehouse !== null && $buyer['street'] !== '') {
            $builder->withDeliveryAddress(
                $buyer['street'],
                $buyer['zip'],
                $buyer['city'],
                $buyer['country'],
                trim((string) $warehouse->name) ?: null,
                null,
                trim((string) $warehouse->location_note) ?: null,
            );
        }

        if ($order->expected_at !== null) {
            $builder->withRequestedDeliveryPeriod(new DateTimeImmutable($order->expected_at->toDateString()));
        }

        $note = trim((string) $order->note);
        if ($note !== '') {
            $builder->addNote($note);
        }

        $position = 0;
        foreach ($order->lines as $line) {
            $position++;
            $builder->addOrderLine($this->mapLine($line, (string) $position, $currency));
        }

        $document = $builder->build();

        // Frachtkosten → Zuschlag (UGL POZ Typ 07; UBL/Order-X als ChargeTotal).
        // Exportformat bleibt auf Cent gerundet wie bisher (Money::of hatte Skala 2).
        $freight = $order->freight_cost?->withScale(2) ?? Money::zero($document->getCurrency());
        if ($freight->isPositive()) {
            $document->addAllowanceCharge(AllowanceCharge::shipping($freight));
        }

        return $document;
    }

    /**
     * Mappt eine Bestellzeile auf eine Toolkit-{@see OrderLine} inkl.
     * Lieferanten-SKU (Verkäufer-Artikel), eigener Artikelnummer (Käufer-Artikel)
     * und GTIN.
     */
    private function mapLine(PurchaseOrderLine $line, string $id, CurrencyCode $currency): OrderLine {
        $article = $line->article;
        $variant = $line->variant;

        $name = trim((string) $article->name);
        if ($name === '') {
            $name = trim((string) $line->description) ?: 'Artikel';
        }

        $variantName = trim((string) $variant?->name);
        $description = $variantName !== '' ? $variantName : (trim((string) $line->description) ?: null);
        if ($description === $name) {
            $description = null;
        }

        $sellersItemId = trim((string) $line->supplier_sku) ?: trim((string) $variant?->sku) ?: null;
        $buyersItemId = trim((string) $article->number) ?: null;
        $gtin = trim((string) $variant?->gtin) ?: trim((string) $article->gtin) ?: null;

        $quantity = ($line->ordered_qty?->getValue()->toFloat() ?? 0.0);
        $unitPrice = $line->unit_price?->withScale(2) ?? Money::zero($currency);

        return new OrderLine(
            id: $id,
            quantity: $quantity,
            unitCode: $this->unitCode((string) $line->unit),
            netAmount: $unitPrice->times($quantity),
            itemName: $name,
            unitPrice: $unitPrice,
            itemDescription: $description,
            sellersItemId: $sellersItemId,
            buyersItemId: $buyersItemId,
            standardItemId: $gtin,
            standardItemScheme: $gtin !== null ? self::GTIN_SCHEME : null,
            note: trim((string) $line->note) ?: null, // → UGL POT
        );
    }

    /**
     * Käuferstammdaten der eigenen Organisation aus
     * `organizations.settings['einvoice']` (Fallback Name ⇒ Org-Name, Land ⇒ DE).
     *
     * @return array{name: string, street: string, zip: string, city: string,
     *               country: string, vat_id: string, contact_email: string}
     */
    private function buyerData(PurchaseOrder $order): array {
        $organization = $order->organization;
        $settings = $organization !== null && is_array($organization->settings) ? $organization->settings : [];
        $einvoice = is_array($settings['einvoice'] ?? null) ? $settings['einvoice'] : [];

        $get = static fn (string $key): string => trim((string) ($einvoice[$key] ?? ''));

        $name = $get('seller_name');
        if ($name === '' && $organization !== null) {
            $name = trim((string) $organization->name);
        }

        return [
            'name' => $name,
            'street' => $get('street'),
            'zip' => $get('zip'),
            'city' => $get('city'),
            'country' => strtoupper($get('country') ?: 'DE'),
            'vat_id' => $get('vat_id'),
            'contact_email' => $get('contact_email'),
        ];
    }

    /**
     * Einheiten-Mapping auf UN/ECE-Rec-20-Codes über den zentralen
     * {@see UnitCodeMapper} (Feature 107, W7): Stück ⇒ H87 (historischer
     * Bestell-XML-Code), unbekannt ⇒ C62 (generisch „one").
     */
    private function unitCode(string $unit): UnitCode {
        return \App\Support\UnitCodeMapper::tryUnitCode($unit, UnitCode::UNIT_H87) ?? UnitCode::PIECE;
    }
}
