<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XRechnungGenerator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Invoicing\EInvoice;

use App\Models\{Invoice, InvoiceItem};
use CommonToolkit\Enums\CurrencyCode;
use ERechnungToolkit\Builders\ERechnungDocumentBuilder;
use ERechnungToolkit\Entities\{Document, PaymentTerms, TaxSubtotal, TaxTotal};
use ERechnungToolkit\Enums\{ERechnungProfile, InvoiceType, PaymentMeansCode, TaxCategory, UnitCode};
use ERechnungToolkit\Generators\ZugferdPdfGenerator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * E-Rechnung (Feature 045, Abschnitt 8): Adapter zwischen den lokalen
 * Invoice-/Customer-/Org-Daten und dem `php-erechnung-toolkit`.
 *
 *  - XRechnung: UBL-2.1-XML über {@see ERechnungDocumentBuilder} +
 *    `Document::toUblXml()` (Profil {@see ERechnungProfile::XRECHNUNG}).
 *  - ZUGFeRD: PDF/A-3 mit eingebettetem CII-XML über
 *    {@see ZugferdPdfGenerator} (Profil EN 16931 / COMFORT), visuelle
 *    Darstellung aus der bestehenden Rechnungs-PDF-View.
 *
 * Das Toolkit validiert KEINE Geschäftsregeln — {@see preflight()} bleibt
 * die fachliche Validierungsschicht (Fehler blockieren, Warnungen nicht).
 *
 * Ehrliche MVP-Grenzen:
 *  - KEINE Schematron-Validierung (KoSIT-Validator braucht Java) — der
 *    Preflight prüft die fachlichen Pflichtangaben, ersetzt aber keine
 *    vollständige EN-16931-Regelprüfung.
 *  - Gilt nur für den Pfad „WorkDiary führt": bei externer Fakturierungs-
 *    hoheit (Lexoffice/DATEV) liegt die E-Rechnungs-Pflicht beim führenden
 *    Programm (Sperre im Controller via BillingModeResolver).
 *
 * Steuerlogik (Rechnung trägt genau einen Steuersatz, siehe Invoice-Modell):
 *  - Satz > 0  ⇒ Kategorie S (Standard)
 *  - Satz = 0  ⇒ Kategorie Z (Zero rated)
 *  - Org-Flag settings['einvoice']['small_business'] ⇒ Kategorie E
 *    (Exempt, § 19 UStG) mit Exemption-Text.
 */
class XRechnungGenerator {
    /**
     * CustomizationID, wie sie das Toolkit für das XRechnung-Profil emittiert.
     * Seit php-erechnung-toolkit v0.1.12 die korrekte 3.0-Kennung
     * `urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0`.
     */
    public const CUSTOMIZATION_ID = ERechnungProfile::XRECHNUNG->value;

    public const PROFILE_ID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /**
     * Einheiten-Mapping auf UN/ECE-Recommendation-20-Codes (Toolkit-Enum):
     * Stunde ⇒ HOUR (HUR), Stück ⇒ UNIT_H87 (H87, seit Toolkit v0.1.12),
     * unbekannte Einheit (z. B. „Pauschale") ⇒ Default PIECE (C62, generisch
     * „one", EN-16931-konform).
     *
     * @var array<string, UnitCode>
     */
    private const UNIT_CODES = [
        // Stunden
        'h' => UnitCode::HOUR,
        'h.' => UnitCode::HOUR,
        'std' => UnitCode::HOUR,
        'std.' => UnitCode::HOUR,
        'stunde' => UnitCode::HOUR,
        'stunden' => UnitCode::HOUR,
        'hour' => UnitCode::HOUR,
        'hours' => UnitCode::HOUR,
        'hr' => UnitCode::HOUR,
        // Stück ⇒ H87 (UN/ECE Rec 20 „piece")
        'st' => UnitCode::UNIT_H87,
        'st.' => UnitCode::UNIT_H87,
        'stk' => UnitCode::UNIT_H87,
        'stk.' => UnitCode::UNIT_H87,
        'stück' => UnitCode::UNIT_H87,
        'stueck' => UnitCode::UNIT_H87,
        'pc' => UnitCode::UNIT_H87,
        'pcs' => UnitCode::UNIT_H87,
        'pce' => UnitCode::UNIT_H87,
        'pz' => UnitCode::UNIT_H87,
        'ud' => UnitCode::UNIT_H87,
        'piece' => UnitCode::UNIT_H87,
    ];

    private const DEFAULT_PAYMENT_TERMS_DAYS = 14;

    /**
     * Prüft alle Pflichtangaben für eine E-Rechnung. Fehler verhindern die
     * Erzeugung ({@see generate()} wirft dann eine ValidationException),
     * Warnungen erlauben sie.
     *
     * Profilabhängig: BT-10 (BuyerReference) ist nur für die XRechnung
     * Pflicht — ZUGFeRD EN 16931 verlangt sie nicht (dort nur Warnung).
     *
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function preflight(Invoice $invoice, ERechnungProfile $profile = ERechnungProfile::XRECHNUNG): array {
        $errors = [];
        $warnings = [];

        $invoice->loadMissing(['items', 'customer']);
        $seller = $this->sellerData($invoice);
        $customer = $invoice->customer;

        // Status: nur gestellte/bezahlte Rechnungen sind final genug.
        if (! in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID], true)) {
            $errors[] = (string) __('invoicing.einvoice.error.status');
        }

        if ($invoice->items->isEmpty()) {
            $errors[] = (string) __('invoicing.einvoice.error.no_items');
        }

        // BT-10: BuyerReference ist nur in der XRechnung Pflicht.
        if (trim((string) $customer->buyer_reference) === '') {
            if ($profile->isXRechnung()) {
                $errors[] = (string) __('invoicing.einvoice.error.missing_buyer_reference');
            } else {
                $warnings[] = (string) __('invoicing.einvoice.error.missing_buyer_reference');
            }
        }

        // Verkäuferanschrift (BG-5): Name fällt auf den Org-Namen zurück.
        if ($seller['name'] === '') {
            $errors[] = (string) __('invoicing.einvoice.error.missing_seller_field', ['field' => __('settings.einvoice.seller_name')]);
        }
        foreach (['street', 'zip', 'city'] as $key) {
            if ($seller[$key] === '') {
                $errors[] = (string) __('invoicing.einvoice.error.missing_seller_field', ['field' => __('settings.einvoice.' . $key)]);
            }
        }

        // BT-31/BT-32: mindestens USt-IdNr. ODER Steuernummer.
        if ($seller['vat_id'] === '' && $seller['tax_number'] === '') {
            $errors[] = (string) __('invoicing.einvoice.error.missing_tax_id');
        }

        // Zahlweg 58 (SEPA-Überweisung) braucht eine IBAN.
        if ($seller['iban'] === '') {
            $errors[] = (string) __('invoicing.einvoice.error.missing_iban');
        }
        if ($seller['bic'] === '') {
            $warnings[] = (string) __('invoicing.einvoice.warning.missing_bic');
        }

        // Steuersatz: die Rechnung trägt genau einen Satz; 0 % ist gültig (Z/E).
        // (NULL wird durch den String-Cast zu '' — beides fällt hier durch.)
        if (trim((string) $invoice->tax_rate) === '') {
            $errors[] = (string) __('invoicing.einvoice.error.missing_tax_rate');
        }

        // Summen-Konsistenz aus den echten Feldern. Betragstreue: das Toolkit
        // berechnet die Summen selbst aus den Lines (round(qty*price, 2) je
        // Position, Steuer = round(Basis*Satz/100, 2)) — beide Sichten müssen
        // mit den Invoice-Feldern übereinstimmen (Toleranz 0,5 ct).
        if ($invoice->items->isNotEmpty()) {
            $lineSum = round($invoice->items->sum(fn(InvoiceItem $i): float => (float) $i->amount), 2);
            $builderSum = round($invoice->items->sum(
                fn(InvoiceItem $i): float => round((float) $i->quantity * (float) $i->unit_price, 2),
            ), 2);
            $subtotal = round((float) $invoice->subtotal, 2);
            $total = round((float) $invoice->total, 2);
            $taxAmount = round((float) $invoice->tax_amount, 2);
            $builderTax = round($subtotal * ((float) $invoice->tax_rate) / 100, 2);
            if (
                abs($lineSum - $subtotal) > 0.005
                || abs($builderSum - $subtotal) > 0.005
                || abs($builderTax - $taxAmount) > 0.005
                || abs(($subtotal + $taxAmount) - $total) > 0.005
            ) {
                $errors[] = (string) __('invoicing.einvoice.error.totals_mismatch');
            }
        }

        // XRechnung verlangt Verkäufer-Kontakt (BR-DE-2: Name, Telefon, E-Mail).
        if ($seller['contact_name'] === '' || $seller['contact_email'] === '' || $seller['contact_phone'] === '') {
            $warnings[] = (string) __('invoicing.einvoice.warning.missing_seller_contact');
        }

        // Käuferanschrift (BG-8) — für ein valides Dokument nötig, aber der
        // Kunde ist ggf. nachpflegbar; deshalb Warnung statt harter Fehler.
        if (
            trim((string) $customer->address_street) === ''
            || trim((string) $customer->address_zip) === ''
            || trim((string) $customer->address_city) === ''
        ) {
            $warnings[] = (string) __('invoicing.einvoice.warning.buyer_address_incomplete');
        }

        // BT-49: elektronische Empfängeradresse (wir nutzen die E-Mail).
        if (trim((string) $customer->email) === '') {
            $warnings[] = (string) __('invoicing.einvoice.warning.missing_buyer_email');
        }

        if ($invoice->due_on === null) {
            $warnings[] = (string) __('invoicing.einvoice.warning.missing_due_date');
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Erzeugt das UBL-2.1-XML (XRechnung-Profil). Wirft bei Preflight-Fehlern
     * eine ValidationException (Key `einvoice`); Warnungen blockieren nicht.
     *
     * @throws ValidationException
     */
    public function generate(Invoice $invoice): string {
        $invoice->loadMissing(['items', 'customer']);

        $result = $this->preflight($invoice);
        if ($result['errors'] !== []) {
            throw ValidationException::withMessages(['einvoice' => $result['errors']]);
        }

        return $this->buildDocument($invoice, ERechnungProfile::XRECHNUNG)->toUblXml();
    }

    /**
     * Ob die ZUGFeRD-PDF-Erzeugung verfügbar ist (php-pdf-toolkit installiert).
     */
    public function zugferdAvailable(): bool {
        return (new ZugferdPdfGenerator())->isAvailable();
    }

    /**
     * Erzeugt ein ZUGFeRD-PDF (PDF/A-3, Profil EN 16931 mit eingebettetem
     * CII-XML). `$visualHtml` ist die gerenderte Rechnungs-Druckansicht
     * (Blade-View `invoices.pdf`); ohne sie rendert das Toolkit ein
     * generisches Template. Wirft bei Preflight-Fehlern eine
     * ValidationException; gibt null zurück, wenn die PDF-Erzeugung
     * fehlschlägt (z. B. fehlendes php-pdf-toolkit).
     *
     * @throws ValidationException
     */
    public function generateZugferdPdf(Invoice $invoice, ?string $visualHtml = null): ?string {
        $invoice->loadMissing(['items', 'customer']);

        $result = $this->preflight($invoice, ERechnungProfile::EN16931);
        if ($result['errors'] !== []) {
            throw ValidationException::withMessages(['einvoice' => $result['errors']]);
        }

        $document = $this->buildDocument($invoice, ERechnungProfile::EN16931);

        return (new ZugferdPdfGenerator())->generate($document, $visualHtml);
    }

    /**
     * Gemeinsamer Dokument-Aufbau für XRechnung (UBL) und ZUGFeRD (CII):
     * Org-Settings ⇒ Seller, Customer ⇒ Buyer, Items ⇒ Lines. Das Toolkit
     * berechnet TaxTotal/LegalMonetaryTotal selbst aus den Lines — die
     * Betragstreue gegenüber den Invoice-Feldern sichert der Preflight.
     */
    private function buildDocument(Invoice $invoice, ERechnungProfile $profile): Document {
        $seller = $this->sellerData($invoice);
        $customer = $invoice->customer;
        $currency = CurrencyCode::tryFrom(strtoupper((string) $invoice->currency ?: 'EUR')) ?? CurrencyCode::Euro;
        $category = $this->taxCategory($invoice, $seller);
        $taxRate = (float) $invoice->tax_rate;

        $issuedOn = $invoice->issued_on ?? now();
        $dueOn = $invoice->due_on ?? $issuedOn->copy()->addDays($seller['payment_terms_days']);

        $builder = ERechnungDocumentBuilder::create((string) $invoice->number)
            ->withProfile($profile)
            ->withCurrency($currency)
            ->withIssueDate(new \DateTimeImmutable($issuedOn->toDateString()))
            ->withDueDate(new \DateTimeImmutable($dueOn->toDateString()))
            ->withSeller($seller['name'], $seller['vat_id'], $seller['tax_number'] !== '' ? $seller['tax_number'] : null)
            ->withSellerAddress($seller['street'], $seller['zip'], $seller['city'], $seller['country'])
            ->withBuyer((string) ($customer->company ?: $customer->name), trim((string) $customer->vat_id) !== '' ? trim((string) $customer->vat_id) : null)
            ->withBuyerAddress(
                trim((string) $customer->address_street),
                trim((string) $customer->address_zip),
                trim((string) $customer->address_city),
                strtoupper(trim((string) $customer->country) ?: 'DE'),
            )
            ->withPaymentMeans(PaymentMeansCode::SEPA_CREDIT_TRANSFER)
            ->withPaymentTerms(new PaymentTerms(
                note: (string) __('invoicing.einvoice.payment_terms', ['days' => $seller['payment_terms_days']]),
                netPaymentDays: $seller['payment_terms_days'],
            ));

        // 381 = Gutschrift (Korrekturrechnung); BT-25 verweist aufs Original.
        if ($invoice->isCreditNote()) {
            $builder->withInvoiceType(InvoiceType::CREDIT_NOTE);
            $precedingNumber = trim((string) $invoice->parent?->number);
            if ($precedingNumber !== '') {
                $builder->withPrecedingInvoiceReference($precedingNumber);
            }
        }

        // BT-34: elektronische Adresse des Verkäufers (Schema EM = E-Mail).
        if ($seller['contact_email'] !== '') {
            $builder->withSellerEndpoint($seller['contact_email'], 'EM');
        }

        // BG-6: Verkäufer-Kontakt (BR-DE-2). Fehlt der Kontaktname, fällt er
        // auf den Firmennamen zurück (leere Elemente vermeiden).
        if ($seller['contact_name'] !== '' || $seller['contact_phone'] !== '' || $seller['contact_email'] !== '') {
            $builder->withSellerContact(
                $seller['contact_name'] !== '' ? $seller['contact_name'] : $seller['name'],
                $seller['contact_phone'] !== '' ? $seller['contact_phone'] : null,
                $seller['contact_email'] !== '' ? $seller['contact_email'] : null,
            );
        }

        if ($seller['iban'] !== '') {
            $builder->withSellerBankAccount($seller['iban'], $seller['bic'] !== '' ? $seller['bic'] : null);
        }

        // BT-49: elektronische Empfängeradresse (Schema EM = E-Mail).
        $buyerEmail = trim((string) $customer->email);
        if ($buyerEmail !== '') {
            $builder->withBuyerEndpoint($buyerEmail, 'EM');
        }

        // BT-10: Käuferreferenz/Leitweg-ID — wird unverändert übernommen
        // (kein Format-Raten; eine Leitweg-ID ist hier schlicht der Wert).
        $buyerReference = trim((string) $customer->buyer_reference);
        if ($buyerReference !== '') {
            $builder->withBuyerReference($buyerReference);
        }

        foreach ($invoice->items as $item) {
            // BT-153 (Name) ist Pflicht; lange Beschreibungen wandern
            // zusätzlich in BT-154 (Description).
            $description = trim((string) $item->description);
            $name = Str::limit($description !== '' ? $description : (string) __('invoicing.service'), 100, '…');
            $builder->addLine(
                $name,
                (float) $item->quantity,
                (float) $item->unit_price,
                $taxRate,
                $this->unitCode((string) $item->unit),
                $category,
                mb_strlen($description) > 100 ? $description : null,
            );
        }

        $document = $builder->build();

        // Kategorie E (§ 19 UStG): der Builder kennt keinen Exemption-Text,
        // das Entity-Modell schon — TaxSubtotals mit Befreiungsgrund neu setzen.
        if ($category === TaxCategory::EXEMPT && $document->getTaxTotal() !== null) {
            $reason = (string) __('invoicing.einvoice.exemption_small_business');
            $subtotals = array_map(
                static fn(TaxSubtotal $s): TaxSubtotal => new TaxSubtotal(
                    $s->getTaxableAmount(),
                    $s->getTaxAmount(),
                    $s->getCategory(),
                    $s->getPercent(),
                    $reason,
                ),
                $document->getTaxTotal()->getSubtotals(),
            );
            $document->setTaxTotal(TaxTotal::fromSubtotals($subtotals, $currency));
        }

        return $document;
    }

    /**
     * Verkäuferstammdaten aus organizations.settings['einvoice'] mit
     * Fallbacks (Name ⇒ Org-Name, Land ⇒ DE, Zahlungsziel ⇒ 14 Tage).
     *
     * @return array{name: string, street: string, zip: string, city: string, country: string,
     *               vat_id: string, tax_number: string, contact_name: string, contact_email: string,
     *               contact_phone: string, iban: string, bic: string, account_holder: string,
     *               payment_terms_days: int, small_business: bool}
     */
    private function sellerData(Invoice $invoice): array {
        $organization = $invoice->organization;
        $settings = $organization !== null && is_array($organization->settings) ? $organization->settings : [];
        $einvoice = is_array($settings['einvoice'] ?? null) ? $settings['einvoice'] : [];

        $get = static fn(string $key): string => trim((string) ($einvoice[$key] ?? ''));

        $name = $get('seller_name');
        if ($name === '' && $organization !== null) {
            $name = trim((string) $organization->name);
        }

        $days = (int) ($einvoice['payment_terms_days'] ?? 0);

        return [
            'name' => $name,
            'street' => $get('street'),
            'zip' => $get('zip'),
            'city' => $get('city'),
            'country' => strtoupper($get('country') ?: 'DE'),
            'vat_id' => $get('vat_id'),
            'tax_number' => $get('tax_number'),
            'contact_name' => $get('contact_name'),
            'contact_email' => $get('contact_email'),
            'contact_phone' => $get('contact_phone'),
            'iban' => strtoupper((string) preg_replace('/\s+/', '', $get('iban'))),
            'bic' => strtoupper((string) preg_replace('/\s+/', '', $get('bic'))),
            'account_holder' => $get('account_holder'),
            'payment_terms_days' => $days > 0 ? $days : self::DEFAULT_PAYMENT_TERMS_DAYS,
            'small_business' => (string) ($einvoice['small_business'] ?? '0') === '1',
        ];
    }

    /**
     * Steuerkategorie nach EN 16931: E (Kleinunternehmer § 19), Z (0 %), S (Standard).
     *
     * @param array<string, mixed> $seller
     */
    private function taxCategory(Invoice $invoice, array $seller): TaxCategory {
        if (($seller['small_business'] ?? false) === true) {
            return TaxCategory::EXEMPT;
        }

        return (float) $invoice->tax_rate > 0 ? TaxCategory::STANDARD : TaxCategory::ZERO_RATED;
    }

    private function unitCode(string $unit): UnitCode {
        return self::UNIT_CODES[mb_strtolower(trim($unit))] ?? UnitCode::PIECE;
    }
}
