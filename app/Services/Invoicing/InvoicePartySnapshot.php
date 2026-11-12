<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePartySnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Services\DocumentDesign\IssuerIdentity;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;

/**
 * Empfänger-/Verkäufer-Snapshot (Feature 066, MVP-162): friert beim
 * Ausstellen die Stammdaten BEIDER Parteien am Beleg ein — spätere
 * Stammdatenänderungen deuten ausgestellte Rechnungen nie um (§ 14 UStG,
 * GoBD). Verkäuferquelle ist dieselbe wie bei der E-Rechnung
 * (XRechnungGenerator::sellerData — keine zweite Leselogik). Gelesen wird der
 * Empfänger über {@see Invoice::recipient()}.
 *
 * @phpstan-type Buyer array{name: string, company: string, street: string, zip: string, city: string, country: string, vat_id: string, email: string, buyer_reference: string}
 */
class InvoicePartySnapshot {
    public function __construct(private readonly XRechnungGenerator $einvoice) {}

    /** @return array{seller: array<string, mixed>, issuer: array<string, mixed>, contact: array{name: string, phone: string, email: string}|null, buyer: Buyer, frozen_at: string} */
    public function capture(Invoice $invoice): array {
        return [
            'seller' => $seller = $this->einvoice->sellerData($invoice),
            // Sichtbare Ausstellerangaben des Belegs (MVP-1609): Register und Rechtszeile stehen nicht in den Verkäuferdaten.
            'issuer' => IssuerIdentity::for($invoice->organization, $seller),
            'contact' => InvoicePdfRenderer::contactPerson($invoice),
            'buyer' => self::buyer($invoice->customer),
            'frozen_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Empfängerangaben eines Kunden in der Form des Snapshots. Werte wie im
     * Stamm (ohne Trimmen und ohne Länder-Vorgabe), damit Entwurf und
     * ausgestellter Beleg gleich drucken; die E-Rechnung setzt ihre Vorgaben selbst.
     *
     * @return Buyer
     */
    public static function buyer(?Customer $customer): array {
        return [
            'name' => (string) ($customer->name ?? ''),
            'company' => (string) ($customer->company ?? ''),
            'street' => (string) ($customer->address_street ?? ''),
            'zip' => (string) ($customer->address_zip ?? ''),
            'city' => (string) ($customer->address_city ?? ''),
            'country' => strtoupper((string) ($customer->country ?? '')),
            'vat_id' => (string) ($customer->vat_id ?? ''),
            'email' => (string) ($customer->email ?? ''),
            'buyer_reference' => (string) ($customer->buyer_reference ?? ''),
        ];
    }
}
