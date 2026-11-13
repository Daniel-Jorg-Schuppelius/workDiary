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
use App\Services\UI\BrandingService;
use App\Support\DocumentLocale;

/**
 * Empfänger-/Verkäufer-Snapshot (Feature 066, MVP-162): friert beim
 * Ausstellen die Stammdaten BEIDER Parteien am Beleg ein — spätere
 * Stammdatenänderungen deuten ausgestellte Rechnungen nie um (§ 14 UStG,
 * GoBD). Verkäuferquelle ist dieselbe wie bei der E-Rechnung
 * (XRechnungGenerator::sellerData — keine zweite Leselogik). Gelesen werden
 * Empfänger, Verkäufer, Rechts- und Bankangaben und Belegsprache über
 * {@see Invoice::recipient()}, {@see XRechnungGenerator::sellerData()},
 * {@see Invoice::issuerLegal()} und {@see Invoice::documentLocale()} (MVP-1680).
 *
 * @phpstan-type Buyer array{name: string, company: string, street: string, zip: string, city: string, country: string, vat_id: string, register_number: string, email: string, buyer_reference: string}
 */
class InvoicePartySnapshot {
    /** Steuerkategorien, die die USt-IdNr. des Käufers verlangen (BR-AE-02, BR-IC-02). */
    private const array BUYER_VAT_ID_CATEGORIES = ['AE', 'K'];

    public function __construct(private readonly XRechnungGenerator $einvoice) {}

    /** @return array{seller: array<string, mixed>, issuer: array<string, mixed>, contact: array{name: string, phone: string, email: string}|null, buyer: Buyer, legal: array<string, ?string>, locale: string, frozen_at: string} */
    public function capture(Invoice $invoice): array {
        return [
            'seller' => $seller = $this->einvoice->sellerData($invoice),
            // Sichtbare Ausstellerangaben des Belegs (MVP-1609): Register und Rechtszeile stehen nicht in den Verkäuferdaten.
            'issuer' => IssuerIdentity::for($invoice->organization, $seller),
            'contact' => InvoicePdfRenderer::contactPerson($invoice),
            'buyer' => self::buyerFor($invoice),
            // Bankblock, Absenderzeile und Belegsprache des Nachdrucks (MVP-1680).
            'legal' => app(BrandingService::class)->legalFor($invoice->organization),
            'locale' => DocumentLocale::for($invoice->customer, $invoice->organization),
            'frozen_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Empfänger eines Belegs vor dem Einfrieren: der heutige Kunde. Storno und
     * Gutschrift zu Reverse Charge oder innergemeinschaftlicher Lieferung
     * übernehmen die USt-IdNr. des Originals, wenn der Kunde heute keine mehr
     * führt (MVP-1681), ebenso eine fehlende Registerkennung (MVP-1686) — die
     * Korrektur betrifft den Umsatz an dieses Unternehmen.
     *
     * @return Buyer
     */
    public static function buyerFor(Invoice $invoice): array {
        $buyer = self::buyer($invoice->customer);
        if (trim($buyer['vat_id']) !== '' || ! in_array($invoice->type, [Invoice::TYPE_CANCELLATION, Invoice::TYPE_CREDIT_NOTE], true)) {
            return $buyer;
        }
        $original = $invoice->parent;
        if ($original === null || $original->is($invoice)) {
            return $buyer;
        }
        $categories = $invoice->items->pluck('tax_category')->filter()->map(static fn ($category): string => (string) $category);
        if ((bool) $invoice->is_reverse_charge || $categories->intersect(self::BUYER_VAT_ID_CATEGORIES)->isNotEmpty()) {
            $originalBuyer = $original->recipient();
            $buyer['vat_id'] = $originalBuyer['vat_id'];
            if (trim($buyer['register_number']) === '') {
                $buyer['register_number'] = $originalBuyer['register_number'];
            }
        }

        return $buyer;
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
            // Registerkennung (BT-47, MVP-1686): ersetzt bei § 13b die fehlende USt-IdNr.
            'register_number' => (string) ($customer->register_number ?? ''),
            'email' => (string) ($customer->email ?? ''),
            'buyer_reference' => (string) ($customer->buyer_reference ?? ''),
        ];
    }
}
