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

use App\Enums\Invoicing\{InvoiceStatus, XRechnungSyntax};
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\Organization;
use App\Services\Billing\DocumentTotalsCalculator;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Builders\ERechnungDocumentBuilder;
use ERechnungToolkit\Entities\{Document, PaymentTerms, Period, TaxSubtotal, TaxTotal};
use ERechnungToolkit\Enums\{ERechnungProfile, InvoiceType, PaymentMeansCode, TaxCategory, UnitCode};
use ERechnungToolkit\Generators\ZugferdPdfGenerator;
use ERechnungToolkit\Validators\DocumentRulesValidator;
use Illuminate\Support\{Carbon, Str};
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
 * Kategorien ohne Steuer (AE, K, G, O, E) tragen je Steueraufriss ihren
 * Befreiungsgrund ({@see exemptionReason()}, MVP-1623).
 */
class XRechnungGenerator {
    /**
     * CustomizationID, wie sie das Toolkit für das XRechnung-Profil emittiert.
     * Seit php-erechnung-toolkit v0.1.12 die korrekte 3.0-Kennung
     * `urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0`.
     */
    public const string CUSTOMIZATION_ID = ERechnungProfile::XRECHNUNG->value;

    public const string PROFILE_ID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    private const int DEFAULT_PAYMENT_TERMS_DAYS = 14;

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
        $buyer = $invoice->recipient();

        // Status: nur gestellte/bezahlte Rechnungen sind final genug.
        if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Paid], true)) {
            $errors[] = (string) __('invoicing.einvoice.error.status');
        }

        if ($invoice->pricedItems()->isEmpty()) {
            $errors[] = (string) __('invoicing.einvoice.error.no_items');
        }

        // BT-10: BuyerReference ist in der XRechnung und für Behördenempfänger Pflicht (Feature 196).
        $recipient = app(RecipientProfiles::class)->for($customer, $invoice->organization);
        if ($this->buyerReference($invoice) === '') {
            if ($profile->isXRechnung() || $recipient->buyerReferenceRequired()) {
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

        // Pflichtangaben je Steuerkategorie des Aufrisses (BG-23, MVP-1623).
        $headerCategory = $this->taxCategory($invoice, $seller);
        $categories = $this->lineCategories($invoice, $headerCategory);
        $has = static fn (TaxCategory $category): bool => in_array($category, $categories, true);
        // Eine Kategorie ohne Steuer steht in der E-Rechnung mit 0 %; rechnet der Beleg Steuer darauf, wichen beide ab.
        if (! (bool) $invoice->is_reverse_charge) {
            foreach ($invoice->pricedItems() as $item) {
                $itemCategory = $this->itemTaxCategory($item, $headerCategory);
                $rate = $item->tax_rate ?? $invoice->tax_rate;
                if (! $itemCategory->isTaxable() && $rate !== null && $rate->isPositive()) {
                    $errors[] = (string) __('invoicing.einvoice.error.line_category_rate', [
                        'line' => Str::limit(trim((string) $item->description), 40, '…'),
                        'category' => $itemCategory->value,
                        'rate' => NumberHelper::toGermanFormat((float) $rate->getNumericValue(), 2),
                    ]);
                }
            }
        }
        $buyerVatId = trim($buyer['vat_id']);
        // BR-AE-02 bis -04: Reverse Charge verlangt die USt-IdNr. des Käufers (BT-48), auch im Inland nach § 13b.
        if ($has(TaxCategory::REVERSE_CHARGE) && $buyerVatId === '') {
            $errors[] = (string) __('invoicing.einvoice.error.reverse_charge_buyer_vat_id');
        }
        if ($has(TaxCategory::INTRA_COMMUNITY)) {
            if ($buyerVatId === '') {
                $errors[] = (string) __('invoicing.einvoice.error.intra_community_buyer_vat_id');
            }
            // BR-IC-12: Das Toolkit gibt das Lieferland (BT-80) noch nicht aus.
            $warnings[] = (string) __('invoicing.einvoice.warning.intra_community_delivery_country');
        }
        // BR-IC-02, BR-G-02: innergemeinschaftliche Lieferung und Ausfuhr nennen die USt-IdNr. des Verkäufers.
        if (($has(TaxCategory::INTRA_COMMUNITY) || $has(TaxCategory::EXPORT)) && $seller['vat_id'] === '') {
            $errors[] = (string) __('invoicing.einvoice.error.seller_vat_id_required');
        }
        // BR-O-11 bis -14: „nicht steuerbar“ steht allein; BR-O-02 verbietet die USt-IdNr., es bleibt die Steuernummer.
        if ($has(TaxCategory::OUT_OF_SCOPE)) {
            if (count($categories) > 1) {
                $errors[] = (string) __('invoicing.einvoice.error.not_subject_mixed');
            }
            if ($seller['tax_number'] === '') {
                $errors[] = (string) __('invoicing.einvoice.error.not_subject_tax_number');
            }
            // BR-O-05: Das Toolkit schreibt den Positionssatz (BT-152) auch bei „nicht steuerbar“.
            $warnings[] = (string) __('invoicing.einvoice.warning.not_subject_line_rate');
        }
        // § 14 Abs. 4 Nr. 8 UStG: Steuerbefreiung nach § 4 UStG mit Grund; ohne Regel steht nur „steuerfrei“ im Aufriss.
        if ($has(TaxCategory::EXEMPT) && ! $seller['small_business'] && $this->exemptionRuleNote($invoice) === null) {
            $warnings[] = (string) __('invoicing.einvoice.warning.exemption_unspecified');
        }

        // Zahlweg 58 (SEPA-Überweisung) braucht eine IBAN.
        if ($seller['iban'] === '') {
            $errors[] = (string) __('invoicing.einvoice.error.missing_iban');
        }
        if ($seller['bic'] === '') {
            $warnings[] = (string) __('invoicing.einvoice.warning.missing_bic');
        }

        // Steuersatz: die Rechnung trägt genau einen Satz; 0 % ist gültig (Z/E),
        // nur ein fehlender Satz ist ein Fehler.
        if ($invoice->tax_rate === null) {
            $errors[] = (string) __('invoicing.einvoice.error.missing_tax_rate');
        }

        // Summen-Konsistenz aus den echten Feldern. Betragstreue über den
        // zentralen Kalkulator (MVP-416): Zeilennetto inkl. Positionsrabatt,
        // Belegrabatt-Zuordnung je Satz, Steuer PRO SATZ gerundet — dieselbe
        // Rechenstelle wie Invoice::recalculate() und der XML-Aufbau
        // (Toleranz 0,5 ct).
        if ($invoice->pricedItems()->isNotEmpty()) {
            $totals = $invoice->documentTotals();
            $documentCurrency = $invoice->documentCurrency();
            $builderLineSum = Money::sum($invoice->pricedItems()->map(
                fn (InvoiceItem $i): Money => DocumentTotalsCalculator::lineNet(
                    $i->lineQuantity(),
                    $i->unitPrice(),
                    $i->discountPercent(),
                    $i->discountAmount(),
                    $documentCurrency,
                ),
            )->all(), $documentCurrency);
            $subtotal = $invoice->subtotal ?? Money::zero($documentCurrency);
            $total = $invoice->total ?? Money::zero($documentCurrency);
            $taxAmount = $invoice->tax_amount ?? Money::zero($documentCurrency);
            // Money rechnet exakt — Abweichung heißt Abweichung, keine Toleranz.
            if (
                !$totals['subtotal']->equals($subtotal)
                || !$builderLineSum->equals($totals['line_net_sum'])
                || !$totals['tax_amount']->equals($taxAmount)
                || !$subtotal->plus($taxAmount)->equals($total)
            ) {
                $errors[] = (string) __('invoicing.einvoice.error.totals_mismatch');
            }
        }

        // XRechnung verlangt Verkäufer-Kontakt (BR-DE-2: Name, Telefon,
        // E-Mail) — KoSIT wertet das als FEHLER, der Preflight seit MVP-164
        // konsistent auch (vorher nur Warnung).
        if ($seller['contact_name'] === '' || $seller['contact_email'] === '' || $seller['contact_phone'] === '') {
            if ($profile === ERechnungProfile::XRECHNUNG) {
                $errors[] = (string) __('invoicing.einvoice.warning.missing_seller_contact');
            } else {
                $warnings[] = (string) __('invoicing.einvoice.warning.missing_seller_contact');
            }
        }

        // Käuferanschrift (BG-8) — für ein valides Dokument nötig, aber der
        // Kunde ist ggf. nachpflegbar; deshalb Warnung statt harter Fehler.
        if (trim($buyer['street']) === '' || trim($buyer['zip']) === '' || trim($buyer['city']) === '') {
            $warnings[] = (string) __('invoicing.einvoice.warning.buyer_address_incomplete');
        }

        // BT-49: elektronische Empfängeradresse — Peppol-Teilnehmerkennung
        // (MVP-734) oder ersatzweise die E-Mail. Fehlt beides, ist der Beleg
        // nur für den Papier-/PDF-Weg brauchbar. Zustelladresse, deshalb aus dem heutigen Kunden.
        if (trim((string) $customer->email) === '' && trim((string) $customer->peppol_participant_id) === '') {
            $warnings[] = (string) __('invoicing.einvoice.warning.missing_buyer_email');
        }

        if ($invoice->due_on === null) {
            $warnings[] = (string) __('invoicing.einvoice.warning.missing_due_date');
        }

        // § 14 Abs. 4 Nr. 6 UStG: ohne Leistungsdatum gilt das Rechnungsdatum (UStAE 14.5 Abs. 16).
        if ($this->servicePeriod($invoice) === [null, null]) {
            $warnings[] = (string) __('invoicing.einvoice.warning.service_date_fallback');
        }

        [$documentErrors, $documentWarnings] = $this->supportingDocumentFindings($invoice, $profile);

        return ['errors' => [...$errors, ...$documentErrors], 'warnings' => [...$warnings, ...$documentWarnings]];
    }

    /**
     * Unterlagen, Zeiträume und Größe (Feature 196): Regeln des Toolkits am
     * fertigen Datenmodell, dann die Größe der erzeugten XML gegen die Grenze
     * des Empfängers.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function supportingDocumentFindings(Invoice $invoice, ERechnungProfile $profile): array {
        $errors = [];
        $warnings = [];
        $profiles = app(RecipientProfiles::class);
        $recipient = $profiles->for($invoice->customer, $invoice->organization);
        $documents = $invoice->supportingDocuments()->with('attachment')->get();

        if ($documents->isEmpty()) {
            if ($invoice->status !== InvoiceStatus::Draft
                && $profiles->attachesActivityReport($invoice->customer, $invoice->organization)
                && app(\App\Services\Invoicing\ActivityReportPdfRenderer::class)->hasEntries($invoice)) {
                $warnings[] = (string) __('invoicing.einvoice.warning.activity_report_missing');
            }

            return [$errors, $warnings];
        }

        $service = app(SupportingDocumentService::class);
        $issuer = $service->externalIssuer($invoice);
        if ($issuer !== null) {
            $warnings[] = (string) __('invoicing.supporting_documents.transfer.unsupported', ['program' => app(\App\Services\Numbering\NumberAuthority::class)->sourceLabel($issuer)]);
        }
        foreach ($documents as $document) {
            if ($document->is_embedded && $service->content($document) === null) {
                $errors[] = (string) __('invoicing.supporting_documents.error.integrity', ['reference' => $document->reference]);
            }
        }
        if ($errors !== []) {
            return [$errors, $warnings];
        }

        try {
            $built = $this->buildDocument($invoice, $profile);
        } catch (\Throwable) {
            return [$errors, $warnings]; // die übrigen Preflight-Befunde erklären den Fehlschlag
        }

        foreach ((new DocumentRulesValidator)->validate($built)->getMessages() as $message) {
            $text = (string) __('invoicing.einvoice.rule_violation', ['code' => $message->getCode(), 'text' => $message->getText()]);
            if ($message->isError()) {
                $errors[] = $text;
            } elseif ($message->getSeverity() === \ERechnungToolkit\Enums\ValidationSeverity::WARNING || $recipient === \App\Enums\Invoicing\EInvoiceRecipientProfile::PublicSector) {
                $warnings[] = $text;
            }
        }

        $size = strlen($profile->isXRechnung() && $this->syntaxFor($invoice) !== XRechnungSyntax::Cii ? $built->toUblXml() : $built->toCiiXml());
        $limit = $profiles->limitBytes($invoice->customer, $invoice->organization);
        $sizes = [
            'size' => NumberHelper::formatBytes($size, 1),
            'limit' => NumberHelper::formatBytes($limit, 0),
        ];
        if ($size > $limit) {
            $errors[] = (string) __('invoicing.einvoice.error.too_large', $sizes);
        } elseif ($size >= (int) ($limit * 0.8)) {
            $warnings[] = (string) __('invoicing.einvoice.warning.near_limit', $sizes);
        }

        return [$errors, $warnings];
    }

    /**
     * Erzeugt das XRechnung-XML — UBL 2.1 (Standard) oder UN/CEFACT CII
     * (`XRechnungSyntax::Cii`, MVP-805). Ohne Angabe gilt die Syntax aus dem
     * Zustellformat der Rechnung. Wirft bei Preflight-Fehlern eine
     * ValidationException (Key `einvoice`); Warnungen blockieren nicht.
     *
     * @throws ValidationException
     */
    public function generate(Invoice $invoice, ?XRechnungSyntax $syntax = null): string {
        $invoice->loadMissing(['items', 'customer']);

        $result = $this->preflight($invoice);
        if ($result['errors'] !== []) {
            throw ValidationException::withMessages(['einvoice' => $result['errors']]);
        }

        $document = $this->buildDocument($invoice, ERechnungProfile::XRECHNUNG);

        return ($syntax ?? $this->syntaxFor($invoice)) === XRechnungSyntax::Cii
            ? $document->toCiiXml()
            : $document->toUblXml();
    }

    /** Syntax aus dem Zustellformat der Rechnung; ohne Angabe UBL. */
    public function syntaxFor(Invoice $invoice): XRechnungSyntax {
        // Ungespeicherte Rechnungen tragen das Format mitunter noch nicht.
        $format = $invoice->getAttribute('delivery_format');

        return $format instanceof \App\Enums\Invoicing\InvoiceDeliveryFormat ? $format->xrechnungSyntax() : XRechnungSyntax::Ubl;
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
     * Org-Settings ⇒ Seller, {@see Invoice::recipient()} ⇒ Buyer, Items ⇒ Lines. Das Toolkit
     * berechnet TaxTotal/LegalMonetaryTotal selbst aus den Lines — die
     * Betragstreue gegenüber den Invoice-Feldern sichert der Preflight.
     */
    private function buildDocument(Invoice $invoice, ERechnungProfile $profile): Document {
        $invoice->loadMissing(['items.timeEntries']);
        $seller = $this->sellerData($invoice);
        $customer = $invoice->customer;
        $currency = $invoice->currency ?? CurrencyCode::Euro;
        $category = $this->taxCategory($invoice, $seller);
        $taxRate = $invoice->tax_rate !== null ? (float) $invoice->tax_rate->getNumericValue() : 0.0;
        // BR-O-02: Eine nicht steuerbare Rechnung trägt keine USt-IdNr.; die Steuernummer kennzeichnet den Verkäufer.
        $notSubject = $this->lineCategories($invoice, $category) === [TaxCategory::OUT_OF_SCOPE] && $seller['tax_number'] !== '';
        if ($notSubject) {
            $seller['vat_id'] = '';
        }
        // Käufer wie auf dem Beleg: ausgestellt der eingefrorene Stand (MVP-1624); Name wie displayLabel(): Firma, sonst Name.
        $buyer = $invoice->recipient();
        $buyerVatId = trim($buyer['vat_id']);

        $issuedOn = $invoice->issued_on ?? now();
        $dueOn = $invoice->due_on ?? $issuedOn->copy()->addDays($invoice->effectivePaymentTermsDays());

        $builder = ERechnungDocumentBuilder::create((string) $invoice->number)
            ->withProfile($profile)
            ->withCurrency($currency)
            ->withIssueDate(new \DateTimeImmutable($issuedOn->toDateString()))
            ->withDueDate(new \DateTimeImmutable($dueOn->toDateString()))
            ->withSeller($seller['name'], $seller['vat_id'], $seller['tax_number'] !== '' ? $seller['tax_number'] : null)
            ->withSellerAddress($seller['street'], $seller['zip'], $seller['city'], $seller['country'])
            ->withBuyer($buyer['company'] ?: $buyer['name'], $buyerVatId !== '' && ! $notSubject ? $buyerVatId : null)
            ->withBuyerAddress(
                trim($buyer['street']),
                trim($buyer['zip']),
                trim($buyer['city']),
                strtoupper(trim($buyer['country']) ?: 'DE'),
            )
            ->withPaymentMeans(PaymentMeansCode::SEPA_CREDIT_TRANSFER)
            ->withPaymentTerms($this->paymentTerms($invoice, $invoice->effectivePaymentTermsDays()));

        // 381 = Gutschrift (Korrekturrechnung); BT-25 verweist aufs Original.
        if ($invoice->isCreditNote()) {
            $builder->withInvoiceType(InvoiceType::CREDIT_NOTE);
            $precedingNumber = trim((string) $invoice->parent?->number);
            if ($precedingNumber !== '') {
                $builder->withPrecedingInvoiceReference($precedingNumber);
            }
        }

        // BR-CO-26 verlangt BT-29, BT-30 oder die USt-IdNr. (BT-31). Ohne
        // USt-IdNr. — typisch Kleinunternehmer nach § 19 UStG — trägt die
        // Steuernummer zusätzlich die Verkäuferkennung BT-29; sonst lehnt die
        // Prüfung beim Empfänger die Rechnung ab (MVP-805).
        if ($seller['vat_id'] === '' && $seller['tax_number'] !== '') {
            $builder->withSellerIdentifier($seller['tax_number']);
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

        // BT-49: elektronische Empfängeradresse. Die Peppol-Teilnehmerkennung
        // gewinnt vor der E-Mail (Feature 066, MVP-734) — sie IST die
        // elektronische Adresse im Peppol-Sinn, und der Empfänger-Access-Point
        // prüft BT-49 gegen den Umschlag. Schema ist der ICD (z. B. 9930),
        // nicht „EM". Ohne Kennung bleibt es bei der E-Mail. Als Zustelladresse
        // aus dem heutigen Kunden: der Umschlag adressiert den heutigen Teilnehmer.
        $buyerParticipant = \App\Services\Peppol\PeppolParticipantService::forCustomer($customer);
        $buyerEmail = trim((string) $customer->email);
        if ($buyerParticipant !== null) {
            $builder->withBuyerEndpoint($buyerParticipant->getIdentifier(), $buyerParticipant->getIcd());
        } elseif ($buyerEmail !== '') {
            $builder->withBuyerEndpoint($buyerEmail, 'EM');
        }

        // BT-10: Käuferreferenz/Leitweg-ID — wird unverändert übernommen
        // (kein Format-Raten; eine Leitweg-ID ist hier schlicht der Wert).
        $buyerReference = $this->buyerReference($invoice);
        if ($buyerReference !== '') {
            $builder->withBuyerReference($buyerReference);
        }

        // Leistungszeitpunkt (§ 14 Abs. 4 Nr. 6 UStG): ein Tag als BT-72, mehrere als BG-14;
        // ohne Angabe gilt das Rechnungsdatum (UStAE 14.5 Abs. 16).
        [$serviceFrom, $serviceTo] = $this->servicePeriod($invoice);
        if ($serviceFrom === null || $serviceTo === null) {
            $builder->withDeliveryDate(new \DateTimeImmutable($issuedOn->toDateString()));
        } elseif ($serviceFrom->isSameDay($serviceTo)) {
            $builder->withDeliveryDate(new \DateTimeImmutable($serviceFrom->toDateString()));
        } else {
            $builder->withInvoicingPeriod(new \DateTimeImmutable($serviceFrom->toDateString()), new \DateTimeImmutable($serviceTo->toDateString()));
        }

        // Rechnungsbegründende Unterlagen (BG-24, Feature 196) und der Hinweis darauf im Rechnungstext.
        $supportingDocuments = app(SupportingDocumentService::class)->forEInvoice($invoice);
        foreach ($supportingDocuments as $supportingDocument) {
            $builder->addSupportingDocument($supportingDocument);
        }
        if ($supportingDocuments !== []) {
            $builder->addNote((string) __('invoicing.einvoice.note_supporting_documents', [
                'list' => implode(', ', array_map(
                    static fn (\ERechnungToolkit\Entities\SupportingDocument $d): string => trim($d->getReference() . ' ' . ($d->getDescription() ?? '')),
                    $supportingDocuments,
                )),
            ]));
        }

        // BT-22: Arbeitskosten nach § 35a EStG (MVP-1053), wie auf dem PDF.
        $labourCosts = $invoice->disclosedLabourCosts();
        if ($labourCosts !== null && ! $labourCosts['gross']->isZero()) {
            $builder->addNote((string) __('invoicing.labour_costs.einvoice_note', [
                'gross' => NumberHelper::toGermanFormat($labourCosts['gross']->toFloat(), 2),
                'tax' => NumberHelper::toGermanFormat($labourCosts['tax']->toFloat(), 2),
                'currency' => $invoice->documentCurrency()->value,
            ]));
        }

        // Textzeilen der Gliederung (MVP-1054) gehen als Hinweis mit; Titel nicht.
        foreach ($invoice->items as $item) {
            if ($item->lineKind() === \App\Enums\Billing\DocumentLineKind::Text && trim((string) $item->description) !== '') {
                $builder->addNote(trim((string) $item->description));
            }
        }

        $objectIdentifiers = $this->lineObjectIdentifiers($invoice);
        $lineNo = 0;
        foreach ($invoice->pricedItems() as $item) {
            // BT-153 (Name) ist Pflicht; lange Beschreibungen wandern
            // zusätzlich in BT-154 (Description).
            $description = trim((string) $item->description);
            $name = Str::limit($description !== '' ? $description : (string) __('invoicing.service'), 100, '…');
            $itemCategory = $this->itemTaxCategory($item, $category);
            // Ohne Steuer (AE, K, G, E, Z, O) ist der Zeilensatz 0 % — auch wenn die Position den Regelsatz trägt (BR-AE-05 u. a.).
            $itemRate = ! $itemCategory->isTaxable() ? 0.0 : ($item->tax_rate !== null ? (float) $item->tax_rate->getNumericValue() : $taxRate);

            // MVP-416: Zeilen mit Rabatt als InvoiceLine mit Line-Allowance (BG-27):
            // BT-131 = rabattiertes Zeilennetto, die Allowance erklärt die Differenz
            // zu Menge × Preis (BR-24-konform). Zeilen ohne Rabatt identisch zum
            // bisherigen addLine-Pfad.
            $lineNo++;
            $line = new \ERechnungToolkit\Entities\InvoiceLine(
                id: (string) $lineNo,
                quantity: (float) $item->quantity,
                unitCode: $this->unitCode((string) $item->unit),
                netAmount: $item->amount ?? Money::zero($currency),
                itemName: $name,
                unitPrice: $item->unit_price?->withScale(2) ?? Money::zero($currency),
                taxCategory: $itemCategory,
                taxPercent: $itemRate,
                itemDescription: mb_strlen($description) > 100 ? $description : null,
                note: $this->lineNote($item),
                period: $this->linePeriod($item),
                objectIdentifier: $objectIdentifiers[$item->id] ?? null,
            );
            $lineDiscount = ($item->unit_price?->withScale(2) ?? Money::zero($currency))
                ->times((float) $item->quantity)
                ->minus($item->amount ?? Money::zero($currency));
            if ($lineDiscount->isPositive()) {
                $line->addAllowanceCharge(\ERechnungToolkit\Entities\AllowanceCharge::discount(
                    $lineDiscount,
                    (string) __('Rabatt'),
                    taxCategory: $itemCategory,
                    taxPercent: $itemRate,
                ));
            }
            $builder->addInvoiceLine($line);
        }

        $document = $builder->build();

        // MVP-416: Belegrabatt als Document-Allowance (BG-20) — je Steuersatz
        // anteilig (derselbe Kalkulator wie Invoice::recalculate/Preflight);
        // addAllowanceCharge() rechnet TaxTotal/MonetaryTotal selbst neu.
        $totals = $invoice->documentTotals();
        if ($totals['document_discount']->isPositive()) {
            $categoriesByRate = [];
            foreach ($invoice->pricedItems() as $item) {
                $rateKey = NumberHelper::toUSFormat($item->tax_rate !== null ? (float) $item->tax_rate->getNumericValue() : $taxRate, 2);
                $categoriesByRate[$rateKey] ??= $this->itemTaxCategory($item, $category);
            }
            foreach ($totals['by_rate'] as $rateKey => $group) {
                if (!$group['allowance']->isPositive()) {
                    continue;
                }
                $allowanceCategory = $categoriesByRate[$rateKey] ?? $category;
                $document->addAllowanceCharge(\ERechnungToolkit\Entities\AllowanceCharge::discount(
                    $group['allowance'],
                    (string) __('Rabatt'),
                    taxCategory: $allowanceCategory,
                    taxPercent: $allowanceCategory->isTaxable() ? $group['rate'] : 0.0,
                ));
            }
        }

        // Befreiungsgrund je Steueraufriss (BT-120/BT-121): Der Builder kennt nur einen Grund für alle
        // Kategorien ohne Steuer (auch Z, dort verboten), das Entity-Modell einen je Aufriss (MVP-1623).
        if ($document->getTaxTotal() !== null) {
            $subtotals = array_map(
                function (TaxSubtotal $s) use ($invoice, $seller): TaxSubtotal {
                    [$reason, $code] = $this->exemptionReason($s->getCategory(), $invoice, $seller) ?? [null, null];

                    return new TaxSubtotal($s->getTaxableAmount(), $s->getTaxAmount(), $s->getCategory(), $s->getPercent(), $reason, $code);
                },
                $document->getTaxTotal()->getSubtotals(),
            );
            $document->setTaxTotal(TaxTotal::fromSubtotals($subtotals, $currency));
        }

        return $document;
    }

    /**
     * Befreiungsgrund (BT-120) und VATEX-Code (BT-121) einer Steuerkategorie
     * ohne Steuer; null für Kategorien mit Steuer und für Z (BR-Z-10).
     *
     * @param array<string, mixed> $seller
     * @return array{0: string, 1: ?string}|null
     */
    private function exemptionReason(TaxCategory $category, Invoice $invoice, array $seller): ?array {
        return match ($category) {
            TaxCategory::REVERSE_CHARGE => [(string) __('invoicing.einvoice.exemption_reverse_charge'), 'VATEX-EU-AE'],
            TaxCategory::INTRA_COMMUNITY => [(string) __('invoicing.einvoice.exemption_intra_community'), 'VATEX-EU-IC'],
            // Der TaxResolver führt jeden Drittlandkunden unter G; bei Dienstleistungen ist der Grund der Leistungsort.
            TaxCategory::EXPORT => [(string) __($invoice->isMaterial() ? 'invoicing.einvoice.exemption_export_goods' : 'invoicing.einvoice.exemption_export_services'), 'VATEX-EU-G'],
            TaxCategory::OUT_OF_SCOPE => [(string) __('invoicing.einvoice.exemption_not_subject'), 'VATEX-EU-O'],
            TaxCategory::EXEMPT => [($seller['small_business'] ?? false) === true
                ? (string) __('invoicing.einvoice.exemption_small_business')
                : ($this->exemptionRuleNote($invoice) ?? (string) __('invoicing.einvoice.exemption_tax_free')), null],
            default => null,
        };
    }

    /**
     * Befreiungsgründe (BT-120) je Steuerkategorie der berechneten Positionen;
     * dieselben Texte druckt das Rechnungs-PDF (§ 14 Abs. 4 Nr. 8, § 14a UStG).
     *
     * @return array<string, string> Kategoriecode => Text
     */
    public function exemptionReasons(Invoice $invoice): array {
        $invoice->loadMissing(['items', 'organization']);
        $seller = $this->sellerData($invoice);
        $reasons = [];
        foreach ($this->lineCategories($invoice, $this->taxCategory($invoice, $seller)) as $category) {
            $reason = $this->exemptionReason($category, $invoice, $seller);
            if ($reason !== null) {
                $reasons[$category->value] = $reason[0];
            }
        }

        return $reasons;
    }

    /**
     * Befreiungstatbestand steuerfreier Umsätze nach § 4 UStG: Hinweis der Regel
     * mit Satzart `exempt` aus der Steuerregelmatrix zum Stichtag des Steuerfalls.
     */
    private function exemptionRuleNote(Invoice $invoice): ?string {
        $organization = $invoice->organization;
        if ($organization === null) {
            return null;
        }
        $resolvedOn = (string) data_get($invoice->tax_context, 'resolved_on', '');
        $on = $resolvedOn !== '' ? Carbon::parse($resolvedOn) : ($invoice->serviceDateTo() ?? $invoice->issued_on ?? now());
        $resolver = app(\App\Services\Invoicing\TaxResolver::class);
        $note = trim((string) $resolver->ruleFor((int) $organization->id, $resolver->sellerCountry($organization), $invoice->isMaterial() ? 'goods' : 'services', 'exempt', $on)?->note);

        return $note !== '' ? $note : null;
    }

    /**
     * Steuerkategorien der berechneten Positionen (BG-23); ohne Positionen die des Kopfes.
     *
     * @return list<TaxCategory>
     */
    private function lineCategories(Invoice $invoice, TaxCategory $fallback): array {
        $categories = [];
        foreach ($invoice->pricedItems() as $item) {
            $itemCategory = $this->itemTaxCategory($item, $fallback);
            $categories[$itemCategory->value] = $itemCategory;
        }

        return $categories === [] ? [$fallback] : array_values($categories);
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
    public function sellerData(Invoice $invoice): array {
        return $this->sellerDataFor($invoice->organization);
    }

    /**
     * Verkäuferstammdaten einer Organisation — dieselbe Quelle wie
     * {@see sellerData()}, damit es keine zweite Leselogik gibt (auch der
     * GAEB-Export braucht die eigene Anschrift).
     *
     * @return array{name: string, street: string, zip: string, city: string, country: string,
     *               vat_id: string, tax_number: string, contact_name: string, contact_email: string,
     *               contact_phone: string, iban: string, bic: string, account_holder: string,
     *               payment_terms_days: int, small_business: bool}
     */
    public function sellerDataFor(?Organization $organization): array {
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
            // Toolkit-Normalisierung (Vollaudit 2026-07, N40); '' statt null
            // erhält die bisherige Ausgabe-Semantik der Pflichtfeld-Prüfung.
            'iban' => \CommonToolkit\Helper\Data\BankHelper::normalizeIBAN($get('iban')) ?? '',
            'bic' => \CommonToolkit\Helper\Data\BankHelper::normalizeBIC($get('bic')) ?? '',
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
        // Phase 23 (MVP-243, Folge-Lücke 1): Reverse-Charge-Belege sind
        // Kategorie AE — NICHT Z (Zero-rated); eingefrorener Kontext gewinnt.
        if ((bool) $invoice->is_reverse_charge) {
            return TaxCategory::REVERSE_CHARGE;
        }
        $frozen = (string) data_get($invoice->tax_context, 'category', '');
        $fromContext = $frozen !== '' ? TaxCategory::tryFrom($frozen) : null;
        if ($fromContext !== null) {
            return $fromContext;
        }

        return $invoice->tax_rate !== null && $invoice->tax_rate->isPositive() ? TaxCategory::STANDARD : TaxCategory::ZERO_RATED;
    }

    /** Positions-Kategorie (D6): expliziter Positions-Code vor Kopf-Kategorie. */
    private function itemTaxCategory(\App\Models\Invoicing\InvoiceItem $item, TaxCategory $fallback): TaxCategory {
        $code = trim((string) ($item->tax_category ?? ''));

        return $code !== '' ? (TaxCategory::tryFrom($code) ?? $fallback) : $fallback;
    }

    /**
     * UN/ECE-Rec-20-Code aus dem Freitext der Position: Stück ⇒ H87
     * (Geschäftsentscheid, wie Bestellungen), unbekannt ⇒ C62 („one",
     * EN-16931-konform). Die Wortliste liegt im erechnung-toolkit (≥ v0.11.1,
     * Vollscan 2026-08-23 C8) — vorher fielen m/kg/Tag fälschlich auf C62.
     */
    /**
     * Leistungszeitraum über alle Positionen: Zeitraum (Feature 152) oder Leistungsdatum.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function servicePeriod(Invoice $invoice): array {
        $dates = [];
        foreach ($invoice->pricedItems() as $item) {
            $period = $this->linePeriod($item);
            foreach ([$period?->getStart(), $period?->getEnd()] as $date) {
                if ($date !== null) {
                    $dates[] = Carbon::instance($date)->startOfDay();
                }
            }
        }
        if ($dates === []) {
            return [null, null];
        }
        usort($dates, static fn (Carbon $a, Carbon $b): int => $a <=> $b);

        return [$dates[0], $dates[count($dates) - 1]];
    }

    /** Positionszeitraum (BG-26): Zeitraum der Position, sonst ihr Leistungsdatum als ein Tag. */
    private function linePeriod(InvoiceItem $item): ?Period {
        if ($item->service_from !== null) {
            $to = $item->service_to ?? $item->service_from;

            return Period::between(new \DateTimeImmutable($item->service_from->toDateString()), new \DateTimeImmutable($to->toDateString()));
        }

        return $item->service_date !== null ? Period::day(new \DateTimeImmutable($item->service_date->toDateString())) : null;
    }

    /** Positionsnotiz (BT-127) für gebündelte Zeiten: Anzahl der Einsätze und ihr Zeitraum. */
    private function lineNote(InvoiceItem $item): ?string {
        $entries = $item->relationLoaded('timeEntries') ? $item->timeEntries : $item->timeEntries()->get();
        if ($entries->count() < 2) {
            return null;
        }
        $days = $entries->map(static fn ($e): ?Carbon => $e->started_at ?? $e->date)->filter()->sort()->values();
        if ($days->isEmpty()) {
            return (string) __('invoicing.einvoice.line_note_entries_count', ['count' => $entries->count()]);
        }

        return (string) __('invoicing.einvoice.line_note_entries', [
            'count' => $entries->count(),
            'from' => \App\Support\CarbonFmt::fdate($days->first()),
            'to' => \App\Support\CarbonFmt::fdate($days->last()),
        ]);
    }

    /**
     * Objektkennung je Position (BT-128): die Nummer der Aufgabe im Fremdsystem
     * (etwa ein Ticket), wenn alle Zeiten der Position an derselben Aufgabe hängen.
     *
     * @return array<int, string> Positions-ID => Kennung
     */
    private function lineObjectIdentifiers(Invoice $invoice): array {
        $taskByItem = [];
        foreach ($invoice->pricedItems() as $item) {
            $entries = $item->relationLoaded('timeEntries') ? $item->timeEntries : $item->timeEntries()->get();
            $taskIds = $entries->pluck('task_id')->filter()->unique()->values();
            if ($entries->isNotEmpty() && $taskIds->count() === 1 && $entries->every(static fn ($e): bool => $e->task_id !== null)) {
                $taskByItem[$item->id] = (int) $taskIds->first();
            }
        }
        if ($taskByItem === []) {
            return [];
        }

        $references = \App\Models\Integration\ExternalReference::query()
            ->where('referenceable_type', (new \App\Models\Project\Task)->getMorphClass())
            ->whereIn('referenceable_id', array_values(array_unique($taskByItem)))
            ->orderBy('id')
            ->get()
            ->unique('referenceable_id')
            ->keyBy('referenceable_id');

        $identifiers = [];
        foreach ($taskByItem as $itemId => $taskId) {
            $reference = $references->get($taskId);
            $value = $reference !== null ? trim((string) ($reference->external_number ?? $reference->external_id)) : '';
            if ($value !== '') {
                $identifiers[$itemId] = Str::limit($value, 100, '');
            }
        }

        return $identifiers;
    }

    private function unitCode(string $unit): UnitCode {
        return UnitCode::fromText($unit, UnitCode::UNIT_H87) ?? UnitCode::PIECE;
    }

    /** BT-10 darf pro Rechnung überschrieben werden; sonst die Referenz des Empfängers (ausgestellt: eingefroren). */
    private function buyerReference(Invoice $invoice): string {
        $invoiceReference = trim((string) $invoice->buyer_reference);

        return $invoiceReference !== ''
            ? $invoiceReference
            : trim($invoice->recipient()['buyer_reference']);
    }

    /**
     * Zahlungsbedingungen (BT-20): mit Skonto-Kondition (MVP-416) im
     * strukturierten XRechnung-CIUS-Format (BR-DE-18) — jede Angabe als
     * eigene, mit `#` terminierte Zeile; sonst der bisherige Freitext.
     */
    private function paymentTerms(Invoice $invoice, int $netDays): PaymentTerms {
        if ($invoice->hasSkonto()) {
            $skontoPercent = $invoice->skonto_percent !== null ? (float) $invoice->skonto_percent->getNumericValue() : 0.0;

            return new PaymentTerms(
                note: sprintf(
                    '#SKONTO#TAGE=%d#PROZENT=%s#',
                    (int) $invoice->skonto_days,
                    NumberHelper::toUSFormat($skontoPercent, 2),
                ),
                netPaymentDays: $netDays,
                discountPercent: $skontoPercent,
                discountDays: (int) $invoice->skonto_days,
            );
        }

        return new PaymentTerms(
            note: (string) __('invoicing.einvoice.payment_terms', ['days' => $netDays]),
            netPaymentDays: $netDays,
        );
    }
}
