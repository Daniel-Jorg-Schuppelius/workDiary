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

use App\Models\{Customer, Invoice, InvoiceItem};
use Illuminate\Validation\ValidationException;

/**
 * E-Rechnung (Feature 045, Abschnitt 8): erzeugt aus einer lokalen
 * Ausgangsrechnung ein XRechnung-konformes UBL-2.1-Invoice-XML (EN 16931,
 * CIUS XRechnung 3.0) — ohne Paket, per DOMDocument.
 *
 * Ehrliche MVP-Grenzen:
 *  - KEIN ZUGFeRD (PDF/A-3-Einbettung) — bewusster Folgeausbau.
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
    public const CUSTOMIZATION_ID = 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0';

    public const PROFILE_ID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    private const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    /**
     * Einheiten-Mapping auf UN/ECE-Recommendation-20-Codes:
     * Stunde ⇒ HUR, Stück ⇒ H87, Default (z. B. Pauschale) ⇒ C62.
     */
    private const UNIT_CODES = [
        // Stunden
        'h' => 'HUR',
        'h.' => 'HUR',
        'std' => 'HUR',
        'std.' => 'HUR',
        'stunde' => 'HUR',
        'stunden' => 'HUR',
        'hour' => 'HUR',
        'hours' => 'HUR',
        'hr' => 'HUR',
        // Stück
        'st' => 'H87',
        'st.' => 'H87',
        'stk' => 'H87',
        'stk.' => 'H87',
        'stück' => 'H87',
        'stueck' => 'H87',
        'pc' => 'H87',
        'pcs' => 'H87',
        'pce' => 'H87',
        'pz' => 'H87',
        'ud' => 'H87',
        'piece' => 'H87',
    ];

    private const DEFAULT_UNIT_CODE = 'C62';

    private const DEFAULT_PAYMENT_TERMS_DAYS = 14;

    private \DOMDocument $doc;

    /**
     * Prüft alle Pflichtangaben für eine XRechnung. Fehler verhindern die
     * Erzeugung ({@see generate()} wirft dann eine ValidationException),
     * Warnungen erlauben sie.
     *
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function preflight(Invoice $invoice): array {
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

        // BT-10: BuyerReference ist in der XRechnung Pflicht.
        if (trim((string) $customer->buyer_reference) === '') {
            $errors[] = (string) __('invoicing.einvoice.error.missing_buyer_reference');
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

        // Summen-Konsistenz aus den echten Feldern.
        if ($invoice->items->isNotEmpty()) {
            $lineSum = round($invoice->items->sum(fn(InvoiceItem $i): float => (float) $i->amount), 2);
            $subtotal = round((float) $invoice->subtotal, 2);
            $total = round((float) $invoice->total, 2);
            $taxAmount = round((float) $invoice->tax_amount, 2);
            if (abs($lineSum - $subtotal) > 0.005 || abs(($subtotal + $taxAmount) - $total) > 0.005) {
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
     * Erzeugt das UBL-2.1-Invoice-XML. Wirft bei Preflight-Fehlern eine
     * ValidationException (Key `einvoice`); Warnungen blockieren nicht.
     *
     * @throws ValidationException
     */
    public function generate(Invoice $invoice): string {
        $invoice->loadMissing(['items', 'customer']);

        $result = $this->preflight($invoice);
        if ($result['errors'] !== []) {
            throw ValidationException::withMessages(['einvoice' => $result['errors']]);
        }

        $seller = $this->sellerData($invoice);
        $customer = $invoice->customer;
        $currency = strtoupper((string) $invoice->currency ?: 'EUR');

        $this->doc = new \DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;

        $root = $this->doc->createElementNS(self::NS_INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $this->doc->appendChild($root);

        $this->cbc($root, 'CustomizationID', self::CUSTOMIZATION_ID);
        $this->cbc($root, 'ProfileID', self::PROFILE_ID);
        $this->cbc($root, 'ID', (string) $invoice->number);
        $this->cbc($root, 'IssueDate', ($invoice->issued_on ?? now())->toDateString());
        $dueOn = $invoice->due_on ?? ($invoice->issued_on ?? now())->copy()->addDays($seller['payment_terms_days']);
        $this->cbc($root, 'DueDate', $dueOn->toDateString());
        // 380 = Handelsrechnung, 381 = Gutschrift (Korrekturrechnung).
        $this->cbc($root, 'InvoiceTypeCode', $invoice->isCreditNote() ? '381' : '380');
        $this->cbc($root, 'DocumentCurrencyCode', $currency);
        $this->cbc($root, 'BuyerReference', trim((string) $customer->buyer_reference));

        $this->appendSupplierParty($root, $seller);
        $this->appendCustomerParty($root, $customer);
        $this->appendPaymentMeans($root, $invoice, $seller);
        $this->appendPaymentTerms($root, $seller);
        $this->appendTaxTotal($root, $invoice, $seller, $currency);
        $this->appendMonetaryTotal($root, $invoice, $currency);

        foreach ($invoice->items as $item) {
            $this->appendInvoiceLine($root, $invoice, $item, $seller, $currency);
        }

        return (string) $this->doc->saveXML();
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
    private function taxCategory(Invoice $invoice, array $seller): string {
        if (($seller['small_business'] ?? false) === true) {
            return 'E';
        }

        return (float) $invoice->tax_rate > 0 ? 'S' : 'Z';
    }

    /** @param array<string, mixed> $seller */
    private function appendSupplierParty(\DOMElement $root, array $seller): void {
        $supplier = $this->cac($root, 'AccountingSupplierParty');
        $party = $this->cac($supplier, 'Party');

        // BT-34: elektronische Adresse des Verkäufers (Schema EM = E-Mail).
        if ((string) $seller['contact_email'] !== '') {
            $endpoint = $this->cbc($party, 'EndpointID', (string) $seller['contact_email']);
            $endpoint->setAttribute('schemeID', 'EM');
        }

        $address = $this->cac($party, 'PostalAddress');
        $this->cbc($address, 'StreetName', (string) $seller['street']);
        $this->cbc($address, 'CityName', (string) $seller['city']);
        $this->cbc($address, 'PostalZone', (string) $seller['zip']);
        $country = $this->cac($address, 'Country');
        $this->cbc($country, 'IdentificationCode', (string) $seller['country']);

        // BT-31 (USt-IdNr., TaxScheme VAT) bzw. BT-32 (Steuernummer, TaxScheme FC).
        if ((string) $seller['vat_id'] !== '') {
            $taxScheme = $this->cac($party, 'PartyTaxScheme');
            $this->cbc($taxScheme, 'CompanyID', (string) $seller['vat_id']);
            $scheme = $this->cac($taxScheme, 'TaxScheme');
            $this->cbc($scheme, 'ID', 'VAT');
        }
        if ((string) $seller['tax_number'] !== '') {
            $taxScheme = $this->cac($party, 'PartyTaxScheme');
            $this->cbc($taxScheme, 'CompanyID', (string) $seller['tax_number']);
            $scheme = $this->cac($taxScheme, 'TaxScheme');
            $this->cbc($scheme, 'ID', 'FC');
        }

        $legal = $this->cac($party, 'PartyLegalEntity');
        $this->cbc($legal, 'RegistrationName', (string) $seller['name']);

        // BG-6: Verkäufer-Kontakt (BR-DE-2 verlangt Name, Telefon, E-Mail).
        if ((string) $seller['contact_name'] !== '' || (string) $seller['contact_email'] !== '' || (string) $seller['contact_phone'] !== '') {
            $contact = $this->cac($party, 'Contact');
            if ((string) $seller['contact_name'] !== '') {
                $this->cbc($contact, 'Name', (string) $seller['contact_name']);
            }
            if ((string) $seller['contact_phone'] !== '') {
                $this->cbc($contact, 'Telephone', (string) $seller['contact_phone']);
            }
            if ((string) $seller['contact_email'] !== '') {
                $this->cbc($contact, 'ElectronicMail', (string) $seller['contact_email']);
            }
        }
    }

    private function appendCustomerParty(\DOMElement $root, Customer $customer): void {
        $buyer = $this->cac($root, 'AccountingCustomerParty');
        $party = $this->cac($buyer, 'Party');

        // BT-49: elektronische Empfängeradresse (Schema EM = E-Mail).
        $email = trim((string) $customer->email);
        if ($email !== '') {
            $endpoint = $this->cbc($party, 'EndpointID', $email);
            $endpoint->setAttribute('schemeID', 'EM');
        }

        $address = $this->cac($party, 'PostalAddress');
        if (trim((string) $customer->address_street) !== '') {
            $this->cbc($address, 'StreetName', trim((string) $customer->address_street));
        }
        if (trim((string) $customer->address_city) !== '') {
            $this->cbc($address, 'CityName', trim((string) $customer->address_city));
        }
        if (trim((string) $customer->address_zip) !== '') {
            $this->cbc($address, 'PostalZone', trim((string) $customer->address_zip));
        }
        $country = $this->cac($address, 'Country');
        $this->cbc($country, 'IdentificationCode', strtoupper(trim((string) $customer->country) ?: 'DE'));

        // BT-48: USt-IdNr. des Käufers (optional).
        $vatId = trim((string) $customer->vat_id);
        if ($vatId !== '') {
            $taxScheme = $this->cac($party, 'PartyTaxScheme');
            $this->cbc($taxScheme, 'CompanyID', $vatId);
            $scheme = $this->cac($taxScheme, 'TaxScheme');
            $this->cbc($scheme, 'ID', 'VAT');
        }

        $legal = $this->cac($party, 'PartyLegalEntity');
        $this->cbc($legal, 'RegistrationName', (string) ($customer->company ?: $customer->name));
    }

    /** @param array<string, mixed> $seller */
    private function appendPaymentMeans(\DOMElement $root, Invoice $invoice, array $seller): void {
        $means = $this->cac($root, 'PaymentMeans');
        // 58 = SEPA-Überweisung (UNTDID 4461).
        $this->cbc($means, 'PaymentMeansCode', '58');
        $this->cbc($means, 'PaymentID', (string) $invoice->number);

        $account = $this->cac($means, 'PayeeFinancialAccount');
        $this->cbc($account, 'ID', (string) $seller['iban']);
        if ((string) $seller['account_holder'] !== '') {
            $this->cbc($account, 'Name', (string) $seller['account_holder']);
        }
        if ((string) $seller['bic'] !== '') {
            $branch = $this->cac($account, 'FinancialInstitutionBranch');
            $this->cbc($branch, 'ID', (string) $seller['bic']);
        }
    }

    /** @param array{payment_terms_days: int} $seller */
    private function appendPaymentTerms(\DOMElement $root, array $seller): void {
        $terms = $this->cac($root, 'PaymentTerms');
        $this->cbc($terms, 'Note', (string) __('invoicing.einvoice.payment_terms', ['days' => $seller['payment_terms_days']]));
    }

    /** @param array<string, mixed> $seller */
    private function appendTaxTotal(\DOMElement $root, Invoice $invoice, array $seller, string $currency): void {
        $category = $this->taxCategory($invoice, $seller);

        $taxTotal = $this->cac($root, 'TaxTotal');
        $this->amount($taxTotal, 'TaxAmount', (float) $invoice->tax_amount, $currency);

        $subtotal = $this->cac($taxTotal, 'TaxSubtotal');
        $this->amount($subtotal, 'TaxableAmount', (float) $invoice->subtotal, $currency);
        $this->amount($subtotal, 'TaxAmount', (float) $invoice->tax_amount, $currency);

        $taxCategory = $this->cac($subtotal, 'TaxCategory');
        $this->cbc($taxCategory, 'ID', $category);
        $this->cbc($taxCategory, 'Percent', $this->decimal((float) $invoice->tax_rate));
        if ($category === 'E') {
            $this->cbc($taxCategory, 'TaxExemptionReason', (string) __('invoicing.einvoice.exemption_small_business'));
        }
        $scheme = $this->cac($taxCategory, 'TaxScheme');
        $this->cbc($scheme, 'ID', 'VAT');
    }

    private function appendMonetaryTotal(\DOMElement $root, Invoice $invoice, string $currency): void {
        $total = $this->cac($root, 'LegalMonetaryTotal');
        $this->amount($total, 'LineExtensionAmount', (float) $invoice->subtotal, $currency);
        $this->amount($total, 'TaxExclusiveAmount', (float) $invoice->subtotal, $currency);
        $this->amount($total, 'TaxInclusiveAmount', (float) $invoice->total, $currency);
        $this->amount($total, 'PayableAmount', (float) $invoice->total, $currency);
    }

    /** @param array<string, mixed> $seller */
    private function appendInvoiceLine(\DOMElement $root, Invoice $invoice, InvoiceItem $item, array $seller, string $currency): void {
        $line = $this->cac($root, 'InvoiceLine');
        $this->cbc($line, 'ID', (string) $item->position);

        $quantity = $this->cbc($line, 'InvoicedQuantity', $this->decimal((float) $item->quantity));
        $quantity->setAttribute('unitCode', $this->unitCode((string) $item->unit));

        $this->amount($line, 'LineExtensionAmount', (float) $item->amount, $currency);

        $itemEl = $this->cac($line, 'Item');
        // BT-153 (Name) ist Pflicht; lange Beschreibungen wandern zusätzlich
        // in BT-154 (Description). UBL-Reihenfolge: Description vor Name.
        $description = trim((string) $item->description);
        $name = \Illuminate\Support\Str::limit($description !== '' ? $description : (string) __('invoicing.service'), 100, '…');
        if (mb_strlen($description) > 100) {
            $this->cbc($itemEl, 'Description', $description);
        }
        $this->cbc($itemEl, 'Name', $name);

        $taxCategory = $this->cac($itemEl, 'ClassifiedTaxCategory');
        $this->cbc($taxCategory, 'ID', $this->taxCategory($invoice, $seller));
        $this->cbc($taxCategory, 'Percent', $this->decimal((float) $invoice->tax_rate));
        $scheme = $this->cac($taxCategory, 'TaxScheme');
        $this->cbc($scheme, 'ID', 'VAT');

        $price = $this->cac($line, 'Price');
        $this->amount($price, 'PriceAmount', (float) $item->unit_price, $currency);
    }

    private function unitCode(string $unit): string {
        return self::UNIT_CODES[mb_strtolower(trim($unit))] ?? self::DEFAULT_UNIT_CODE;
    }

    private function cbc(\DOMElement $parent, string $name, string $value): \DOMElement {
        $el = $this->doc->createElementNS(self::NS_CBC, 'cbc:' . $name);
        $el->appendChild($this->doc->createTextNode($value));
        $parent->appendChild($el);

        return $el;
    }

    private function cac(\DOMElement $parent, string $name): \DOMElement {
        $el = $this->doc->createElementNS(self::NS_CAC, 'cac:' . $name);
        $parent->appendChild($el);

        return $el;
    }

    private function amount(\DOMElement $parent, string $name, float $value, string $currency): \DOMElement {
        $el = $this->cbc($parent, $name, $this->decimal($value));
        $el->setAttribute('currencyID', $currency);

        return $el;
    }

    /** Beträge/Mengen mit 2 Nachkommastellen, Punkt als Dezimaltrenner. */
    private function decimal(float $value): string {
        return number_format($value, 2, '.', '');
    }
}
