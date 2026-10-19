<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingEInvoiceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use CommonToolkit\Helper\Data\{CryptoHelper, StringHelper, XmlHelper};
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\Helper\FileSystem\File;
use ERechnungToolkit\Entities\Document as EInvoiceDocument;
use ERechnungToolkit\Enums\ERechnungProfile;
use ERechnungToolkit\Parsers\{ERechnungParser, ZugferdPdfParser};
use ERechnungToolkit\Validators\{CiiSchemaValidator, UblSchemaValidator};
use ERRORToolkit\Exceptions\FileSystem\FileNotWrittenException;
use SimpleXMLElement;
use Throwable;

/**
 * Eingangs-E-Rechnung (Nachtrag 045b): parst XRechnung/ZUGFeRD über das
 * php-erechnung-toolkit — XML direkt (ERechnungParser, mit eingebauter
 * UBL/CII-Formaterkennung), PDF über den eingebetteten ZUGFeRD-Anhang
 * (ZugferdPdfParser, braucht das pdf-toolkit). Die Rechnung wird NICHT als
 * lokale Invoice übernommen (Rechnungshoheit beim externen Programm) —
 * nur angezeigt und als Document (Typ Rechnung) im DMS abgelegt.
 */
class IncomingEInvoiceService {
    /** Kleinere Bilder sind Logos oder Signaturbilder, keine fotografierten Belege. */
    private const MIN_IMAGE_BYTES = 20_000;

    /**
     * Versucht, Dateiinhalt als E-Rechnung zu parsen. `null`, wenn es keine
     * (lesbare) E-Rechnung ist — Aufrufer behandeln das als „normale Datei".
     */
    public function parse(string $contents, ?string $mime = null, ?string $path = null): ?EInvoiceDocument {
        $kind = self::contentKind($contents, $mime);
        if ($kind === 'pdf') {
            return $this->parsePdf($contents, $path);
        }
        if ($kind !== 'xml') {
            return null;
        }

        try {
            return (new ERechnungParser)->parse($contents);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Rohes Rechnungs-XML des Eingangs (MVP-166): XML-Uploads direkt,
     * ZUGFeRD-PDFs über die Toolkit-Extraktion (null = nicht extrahierbar).
     */
    public function extractXml(string $contents, ?string $mime = null, ?string $path = null): ?string {
        $kind = self::contentKind($contents, $mime);
        if ($kind !== 'pdf') {
            return $kind === 'xml' ? $contents : null;
        }
        $parser = new ZugferdPdfParser;
        if (! $parser->isAvailable()) {
            return null;
        }

        try {
            // Kanäle ohne Datei (Mail, Peppol, API) liefern nur Bytes — bisher
            // blieb deren ZUGFeRD-XML deshalb ungeprüft.
            return $path !== null && File::isFile($path)
                ? $parser->extractXml($path)
                : $parser->extractXmlFromString($contents);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * PDF oder XML am Inhalt entschieden, nicht am gemeldeten Typ: Absender
     * melden PDFs als `application/x-download`, vor `%PDF-` darf Vorspann
     * stehen, und `<<` steht in jedem PDF-Kopf — ein Präfix-Sniff gab solche
     * PDFs als „XML“ an den E-Rechnungs-Parser. XML ohne Prolog meldet finfo
     * als Text, deshalb entscheidet dort die erste Klammer.
     *
     * @return 'pdf'|'xml'|null
     */
    private static function contentKind(string $contents, ?string $mime): ?string {
        if ($contents === '') {
            return null;
        }
        if (File::mimeTypeFromContent($contents) === 'application/pdf') {
            return 'pdf';
        }
        if (str_starts_with(ltrim(StringHelper::stripBom($contents), " \t\r\n"), '<')) {
            return 'xml';
        }

        return $mime !== null && str_contains($mime, 'pdf') ? 'pdf' : null;
    }

    /**
     * Eingangs-Validierung (MVP-166): XSD der tatsächlichen Syntax (UBL oder
     * CII, gewählt am Wurzelelement) + KoSIT (XSD/EN-16931/CIUS).
     * Verfügbarkeit wird transparent ausgewiesen. Für Altprofile (ZUGFeRD 1.0)
     * entfällt KoSIT: die XRechnung-Konfiguration kennt kein Szenario dafür,
     * jeder Lauf kostete einen JVM-Start und endete „abgelehnt“ ohne Meldung.
     *
     * @return array{schema_checked: bool, schema_errors: array<int, string>, kosit_applicable: bool, kosit_available: bool, kosit_valid: bool|null, kosit_scenario: string|null, kosit_errors: array<int, string>}
     */
    public function validateXml(string $xml, ?ERechnungProfile $profile = null): array {
        $result = [
            'schema_checked' => false,
            'schema_errors' => [],
            'kosit_applicable' => ! ($profile?->isLegacy() ?? false),
            'kosit_available' => false,
            'kosit_valid' => null,
            'kosit_scenario' => null,
            'kosit_errors' => [],
        ];

        // XXE-gehärteter Root-Sniff für die XSD-Auswahl. Bisher kannte nur das
        // UBL-Schema den Eingang — jede ZUGFeRD-/CII-Rechnung blieb ungeprüft.
        $parsed = XmlHelper::safeLoadString($xml);
        if ($parsed instanceof SimpleXMLElement) {
            $root = $parsed->getName();
            $cii = new CiiSchemaValidator;
            $ubl = new UblSchemaValidator;
            $schema = match (true) {
                $cii->supports($root, dom_import_simplexml($parsed)->namespaceURI) => $cii,
                $ubl->supports($root) => $ubl,
                default => null,
            };
            if ($schema !== null && $schema->isAvailable()) {
                $result['schema_checked'] = true;
                $result['schema_errors'] = $schema->validate($xml);
            }
        }

        if (! $result['kosit_applicable']) {
            return $result;
        }

        $kosit = new \ERechnungToolkit\Validators\KositValidator;
        $result['kosit_available'] = $kosit->isAvailable();
        if ($result['kosit_available']) {
            $report = $kosit->validate($xml);
            $result['kosit_valid'] = $report->isAccepted();
            // null = kein Prüfszenario für dieses Format: abgelehnt ohne Einzelmeldung.
            $result['kosit_scenario'] = $report->getScenarioName();
            $result['kosit_errors'] = array_map('strval', $report->getErrors());
        }

        return $result;
    }

    /**
     * Kernfelder für Anzeige/Flash — die Detailseite parst das Original
     * bei jedem Aufruf erneut (kein eigenes Schema, Quelle bleibt die Datei).
     *
     * @return array{number: string, issue_date: ?string, due_date: ?string, seller: ?string, seller_vat: ?string, seller_tax_number: ?string, seller_country: ?string, seller_email: ?string, buyer: ?string, buyer_vat: ?string, buyer_country: ?string, seller_address: array{street: ?string, zip: ?string, city: ?string, country: ?string}, buyer_address: array{street: ?string, zip: ?string, city: ?string, country: ?string}, type_code: string, tax_breakdown: list<array{category: string, percent: float, net: string, tax: string}>, currency: string, net: string, tax: string, gross: string, profile: string, profile_legacy: bool, lines: int, order_reference: ?string, buyer_reference: ?string, project_reference: ?string, creditor_iban: ?string, creditor_bic: ?string, discount_percent: ?float, discount_days: ?int}
     */
    public function summary(EInvoiceDocument $document): array {
        $seller = $document->getSeller();
        $buyer = $document->getBuyer();

        return [
            'number' => $document->getId(),
            'issue_date' => $document->getIssueDate()->format('Y-m-d'),
            'due_date' => $document->getDueDate()?->format('Y-m-d'),
            'seller' => $seller->getName(),
            'seller_vat' => $seller->getVatId(),
            // MVP-1107: Richtung, Sonderfälle und Positionen je Steuersatz.
            'seller_tax_number' => $seller->getTaxRegistrationId(),
            'seller_country' => $seller->getPostalAddress()?->getCountryCode(),
            'seller_email' => $seller->getContactEmail(),
            'buyer' => $buyer->getName(),
            'buyer_vat' => $buyer->getVatId(),
            'buyer_country' => $buyer->getPostalAddress()?->getCountryCode(),
            // Vorbefüllung „neu aus Belegdaten“ (MVP-1110).
            'seller_address' => self::addressOf($seller),
            'buyer_address' => self::addressOf($buyer),
            'type_code' => $document->getInvoiceType()->value,
            'tax_breakdown' => array_map(static fn (\ERechnungToolkit\Entities\TaxSubtotal $subtotal): array => [
                'category' => $subtotal->getCategory()->value,
                'percent' => $subtotal->getPercent(),
                'net' => $subtotal->getTaxableAmount()->getAmount(),
                'tax' => $subtotal->getTaxAmount()->getAmount(),
            ], array_values($document->getTaxTotal()?->getSubtotals() ?? [])),
            'currency' => $document->getCurrency()->value,
            'net' => $document->getNetAmount()->getAmount(),
            'tax' => $document->getTaxAmount()->getAmount(),
            'gross' => $document->getGrossAmount()->getAmount(),
            'profile' => $document->getProfile()->label(),
            'profile_legacy' => $document->getProfile()->isLegacy(),
            'lines' => $document->countLines(),
            'order_reference' => $document->getOrderReference(),
            'buyer_reference' => $document->getBuyerReference(),
            'project_reference' => $document->getProjectReference(),
            // MVP-609: Zahlungsdaten aus dem Original. Der Payee gewinnt vor
            // dem Verkäufer — genau dafür gibt es das Feld (Factoring).
            'creditor_iban' => $document->getPayee()?->getIban() ?? $document->getSeller()->getIban(),
            'creditor_bic' => $document->getPayee()?->getBic() ?? $document->getSeller()->getBic(),
            'discount_percent' => $document->getPaymentTerms()?->getDiscountPercent(),
            'discount_days' => $document->getPaymentTerms()?->getDiscountDays(),
        ];
    }

    /**
     * Malware-Prüfung der eingehenden Datei über den im Betrieb konfigurierten
     * Treiber (derselbe wie für Hinweisgeber-Anhänge und Bewerbungsunterlagen —
     * ein Scanner, ein Schalter).
     *
     * Nur ein ausdrückliches `Rejected` weist ab. Liefert der Treiber kein
     * Urteil (kein Scanner konfiguriert, Zeitüberschreitung), läuft der Eingang
     * weiter: die Rechnung landet ohnehin in der Prüfliste und wird nie
     * automatisch gebucht. Eine Blockade ohne Urteil würde den Rechnungseingang
     * jeder Installation ohne Scanner stilllegen.
     */
    private function isInfected(string $contents, ?string $mime, ?string $path, ?\Illuminate\Http\UploadedFile $file): bool {
        $driver = app(\App\Services\Security\Scanning\ScanDriver::class);

        $absolute = $file?->getRealPath() ?: $path;
        try {
            // Kanäle ohne Datei (Mail, Peppol, API) liefern nur Bytes.
            $verdict = $absolute !== null && File::isFile($absolute)
                ? $driver->scan($absolute, $mime)
                : File::withTemp($contents, fn(string $temporary) => $driver->scan($temporary, $mime), 'einvoice-scan-');
        } catch (FileNotWrittenException) {
            return false;
        }

        return $verdict === \App\Enums\Whistleblowing\AttachmentScanStatus::Rejected;
    }

    /**
     * Zentrale Eingangsverarbeitung ALLER Kanäle (MVP-165/167): Hash-Dedup
     * je Organisation, Sicherheitsprüfung, Parse bzw. Erkennung, Validierung,
     * Vorschläge/Abweichungen, Ablage als Document (DMS) + Prüfbereich-Datensatz.
     *
     * Nicht erkannte Dateien weist dieser Weg ab: Beim Upload sieht der Mensch
     * die Meldung, in der Cloud-Ablage bleibt die Datei liegen, Peppol
     * quittiert nicht. Nur das Rechnungspostfach legt Klärfälle an
     * ({@see storeMessage()}), weil die Mail sonst in der allgemeinen Inbox landet.
     *
     * @return array{status: 'created'|'discarded'|'duplicate'|'infected'|'unreadable', incoming: ?\App\Models\Invoicing\IncomingEInvoice, document: ?\App\Models\Document\Document}
     */
    public function storeIncoming(
        \App\Models\Platform\User $actor,
        string $contents,
        ?string $mime = null,
        ?string $path = null,
        string $source = 'upload',
        ?\Illuminate\Http\UploadedFile $file = null,
        ?string $originalName = null,
        ?IncomingOrigin $origin = null,
    ): array {
        $sha256 = CryptoHelper::hash($contents);
        $duplicate = $this->duplicateOf((int) $actor->organization_id, $sha256);
        if ($duplicate !== null) {
            return ['status' => 'duplicate', 'incoming' => $duplicate, 'document' => null];
        }

        // Dateisicherheitsprüfung (Feature 066, Eingangsverarbeitung Schritt 3):
        // nach Hash/Dublette, vor dem Parsen. Alle Kanäle laufen hier durch,
        // deshalb sitzt die Prüfung im Dienst und nicht in einem Controller.
        if ($this->isInfected($contents, $mime, $path, $file)) {
            return ['status' => 'infected', 'incoming' => null, 'document' => null];
        }

        $name = $originalName ?? $file?->getClientOriginalName();
        $summary = $this->analyze($actor, $contents, $mime, $path, $name);
        if (self::isUnrecognized($summary)) {
            return ['status' => 'unreadable', 'incoming' => null, 'document' => null];
        }

        $stored = $this->persist($actor, $sha256, $contents, $mime, $source, $file, $name, $summary, $origin);

        return ['status' => $stored['incoming']->exists ? 'created' : 'discarded', ...$stored];
    }

    /**
     * Rechnungspostfach (MVP-1107): alle Anhänge einer Nachricht gemeinsam
     * auswerten, damit eine Rechnung genau ein Eingang wird.
     *
     * - Die XML führt; ein PDF mit derselben Rechnungsnummer (Sichtbeleg,
     *   ZUGFeRD-Doppel) wird Begleitdatei.
     * - Nicht erkannte Anhänge (AGB, Lieferschein) werden Begleitdatei, sobald
     *   die Nachricht eine erkannte Rechnung enthält; sonst Klärfall.
     * - Inline-Teile, Signaturen und kleine Bilder sind nie Belege.
     *
     * @param  list<\App\Services\Mail\MailAttachment>  $attachments
     * @return array{stored: int, unrecognized: int, duplicates: int, infected: int, candidates: int, discarded: int, incomings: list<\App\Models\Invoicing\IncomingEInvoice>}
     */
    public function storeMessage(\App\Models\Platform\User $actor, array $attachments, IncomingOrigin $origin, string $source = 'mail'): array {
        $result = ['stored' => 0, 'unrecognized' => 0, 'duplicates' => 0, 'infected' => 0, 'candidates' => 0, 'discarded' => 0, 'incomings' => []];
        $organizationId = (int) $actor->organization_id;

        $candidates = [];
        $companions = [];
        foreach ($attachments as $attachment) {
            if (self::isInvoiceCandidate($attachment)) {
                $candidates[] = $attachment;
            } else {
                $companions[] = $attachment;
            }
        }
        $result['candidates'] = count($candidates);
        if ($candidates === []) {
            return $result;
        }

        /** @var list<array{attachment: \App\Services\Mail\MailAttachment, sha256: string, summary: array<string, mixed>}> $analysed */
        $analysed = [];
        $seen = [];
        foreach ($candidates as $attachment) {
            $sha256 = CryptoHelper::hash($attachment->content);
            if (isset($seen[$sha256]) || $this->duplicateOf($organizationId, $sha256) !== null) {
                $result['duplicates']++;

                continue;
            }
            $seen[$sha256] = true;
            if ($this->isInfected($attachment->content, $attachment->mime, null, null)) {
                $result['infected']++;

                continue;
            }
            $analysed[] = [
                'attachment' => $attachment,
                'sha256' => $sha256,
                'summary' => $this->analyze($actor, $attachment->content, $attachment->mime, null, $attachment->filename),
            ];
        }

        // Strukturierte Belege zuerst, XML vor PDF: die XML ist das Original.
        usort($analysed, static fn (array $a, array $b): int => self::leadRank($a) <=> self::leadRank($b));

        /** @var array<int, array{item: array{attachment: \App\Services\Mail\MailAttachment, sha256: string, summary: array<string, mixed>}, companions: list<\App\Services\Mail\MailAttachment>}> $leading */
        $leading = [];
        $unrecognized = [];
        foreach ($analysed as $item) {
            if (self::isUnrecognized($item['summary'])) {
                $unrecognized[] = $item;

                continue;
            }
            $number = \App\Models\Invoicing\IncomingEInvoice::numberKey($item['summary']['number'] ?? null);
            foreach ($leading as $index => $lead) {
                if ($number !== null && $number === \App\Models\Invoicing\IncomingEInvoice::numberKey($lead['item']['summary']['number'] ?? null)) {
                    $leading[$index]['companions'][] = $item['attachment'];

                    continue 2;
                }
            }
            $leading[] = ['item' => $item, 'companions' => []];
        }

        if ($leading === []) {
            foreach ($unrecognized as $item) {
                $leading[] = ['item' => $item, 'companions' => []];
            }
        } else {
            foreach ($unrecognized as $item) {
                $companions[] = $item['attachment'];
            }
        }

        foreach ($leading as $lead) {
            $item = $lead['item'];
            $incoming = $this->persist($actor, $item['sha256'], $item['attachment']->content, $item['attachment']->mime, $source, null, $item['attachment']->filename, $item['summary'], $origin)['incoming'];
            if (! $incoming->exists) {
                $result['discarded']++; // Posteingangsregel hat verworfen: kein Eingang, keine Begleitdateien

                continue;
            }
            $result[self::isUnrecognized($item['summary']) ? 'unrecognized' : 'stored']++;
            $result['incomings'][] = $incoming;
            foreach ([...$lead['companions'], ...$companions] as $companion) {
                app(\App\Services\Attachments\FileAttacher::class)->storeContent($incoming, $companion->content, $companion->filename, $companion->mime, (int) $actor->id);
            }
        }

        return $result;
    }

    /** Anhang, der eine Rechnung sein kann: XML, PDF oder ein Bild, das kein Logo ist. */
    public static function isInvoiceCandidate(\App\Services\Mail\MailAttachment $attachment): bool {
        if ($attachment->isInline) {
            return false;
        }
        $name = mb_strtolower($attachment->filename);
        $mime = mb_strtolower($attachment->mime);
        if (str_contains($mime, 'xml') || str_ends_with($name, '.xml') || str_contains($mime, 'pdf') || str_ends_with($name, '.pdf')) {
            return true;
        }
        $isImage = str_starts_with($mime, 'image/') || preg_match('/\.(jpe?g|png|tiff?)$/', $name) === 1;

        return $isImage && $attachment->size() >= self::MIN_IMAGE_BYTES;
    }

    /**
     * @param  array{attachment: \App\Services\Mail\MailAttachment, summary: array<string, mixed>}  $item
     */
    private static function leadRank(array $item): int {
        $recognition = $item['summary']['recognition'] ?? null;
        $isXml = str_contains(mb_strtolower($item['attachment']->mime), 'xml') || str_ends_with(mb_strtolower($item['attachment']->filename), '.xml');

        return match (true) {
            $recognition === \App\Enums\Invoicing\IncomingInvoiceRecognition::Structured->value && $isXml => 0,
            $recognition === \App\Enums\Invoicing\IncomingInvoiceRecognition::Structured->value => 1,
            $recognition === \App\Enums\Invoicing\IncomingInvoiceRecognition::Extracted->value => 2,
            default => 3,
        };
    }

    private function duplicateOf(int $organizationId, string $sha256): ?\App\Models\Invoicing\IncomingEInvoice {
        // Inhaltsbasierter Dedup (MVP-165): identische Datei je Org genau einmal —
        // auch kanalübergreifend (Upload nach Mail bleibt Dublette).
        return \App\Models\Invoicing\IncomingEInvoice::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('sha256', $sha256)
            ->first();
    }

    /**
     * E-Rechnung parsen, sonst aus PDF bzw. Bild erkennen (MVP-1066). Ohne
     * erkannte Werte kommt das Klärfall-Summary ({@see isUnrecognized()}) mit
     * der Diagnose der Erkennung unter `extraction`.
     *
     * @return array<string, mixed>
     */
    private function analyze(\App\Models\Platform\User $actor, string $contents, ?string $mime, ?string $path, ?string $name): array {
        return $this->analyzeFor($actor->organization, $contents, $mime, $path, $name);
    }

    /** @param  array<string, mixed>  $summary */
    private static function isUnrecognized(array $summary): bool {
        return ($summary['recognition'] ?? null) === \App\Enums\Invoicing\IncomingInvoiceRecognition::None->value;
    }

    /**
     * Erkennung ohne handelnden Nutzer — die Nacherkennung läuft aus der Konsole.
     *
     * @return array<string, mixed>
     */
    private function analyzeFor(?\App\Models\Platform\Organization $organization, string $contents, ?string $mime, ?string $path, ?string $name): array {
        $parsed = $this->parse($contents, $mime, $path);
        if ($parsed !== null) {
            $summary = $this->summary($parsed);
            // Eingangs-Validierung (MVP-166): getrennt vom Original abgelegt.
            $xml = $this->extractXml($contents, $mime, $path);
            $summary['validation'] = $xml !== null ? $this->validateXml($xml, $parsed->getProfile()) : null;
            $summary['recognition'] = \App\Enums\Invoicing\IncomingInvoiceRecognition::Structured->value;

            return $summary;
        }

        ['summary' => $summary, 'extraction' => $extraction] = $this->unstructuredSummary($organization, $contents, $mime, $path, $name);
        if ($summary !== null) {
            $summary['validation'] = null;
            $summary['recognition'] = \App\Enums\Invoicing\IncomingInvoiceRecognition::Extracted->value;

            return $summary;
        }

        return [
            ...$this->unrecognizedSummary(),
            'extraction' => $extraction,
            'kind_hint' => $extraction['kind_hint'] ?? null,
            'reminder_for' => array_values((array) ($extraction['reminder_for'] ?? [])),
        ];
    }

    /**
     * Ablage als Document und Prüfbereich-Datensatz. Ohne Summary entsteht ein
     * Klärfall mit dem Original und leeren Werten.
     *
     * @param  array<string, mixed>|null  $summary
     * @return array{incoming: \App\Models\Invoicing\IncomingEInvoice, document: \App\Models\Document\Document}
     */
    private function persist(
        \App\Models\Platform\User $actor,
        string $sha256,
        string $contents,
        ?string $mime,
        string $source,
        ?\Illuminate\Http\UploadedFile $file,
        ?string $originalName,
        ?array $summary,
        ?IncomingOrigin $origin,
    ): array {
        $organizationId = (int) $actor->organization_id;
        $summary ??= $this->unrecognizedSummary();
        $recognition = \App\Enums\Invoicing\IncomingInvoiceRecognition::from((string) $summary['recognition']);

        ['summary' => $summary, 'classification' => $classification] = $this->enrich($actor->organization, $organizationId, $summary);
        // Herkunft für die Posteingangsregeln (MVP-1209): Dateiname, Betreff, Anhangsnamen.
        $summary['file_name'] = $originalName;
        if ($origin?->subject !== null && trim($origin->subject) !== '') {
            $summary['mail_subject'] = mb_substr(trim($origin->subject), 0, 500);
        }
        if ($origin !== null && $origin->attachmentNames !== []) {
            $summary['mail_attachments'] = array_map(static fn (string $name): string => mb_substr($name, 0, 191), $origin->attachmentNames);
        }

        $attributes = [
            ...$this->documentAttributes($recognition, $summary, $originalName ?? $sha256),
            'document_type' => \App\Enums\Document\DocumentType::Invoice->value,
        ];

        $documents = app(\App\Services\Document\DocumentService::class);
        if ($file !== null) {
            $document = $documents->create(null, $actor, $attributes, $file);
        } else {
            // Kanäle ohne UploadedFile (Mail/API): Document-Kopf + Version aus
            // dem Byte-Inhalt — identische Ablage wie beim Upload.
            $document = \App\Models\Document\Document::query()->create([
                'organization_id' => $organizationId,
                'title' => $attributes['title'],
                'document_type' => $attributes['document_type'],
                'status' => \App\Enums\Document\DocumentStatus::Active->value,
                'description' => $attributes['description'],
                'created_by_user_id' => $actor->id,
            ]);
            $documents->addVersionFromContents($document, $actor, $contents, $originalName ?? ('e-rechnung-' . $sha256 . ($mime !== null && str_contains($mime, 'pdf') ? '.pdf' : '.xml')), $mime);
        }

        $incoming = \App\Models\Invoicing\IncomingEInvoice::query()->create([
            'organization_id' => $organizationId,
            'document_id' => $document->id,
            'sha256' => $sha256,
            'source' => $source,
            'sender_email' => $origin?->senderEmail !== null ? mb_substr(mb_strtolower(trim($origin->senderEmail)), 0, 191) : null,
            'source_reference' => $origin?->reference !== null ? mb_substr($origin->reference, 0, 191) : null,
            'received_at' => now(),
            'status' => \App\Enums\Invoicing\IncomingEInvoiceStatus::Received,
            'direction' => $classification['direction'],
            'kind' => $classification['kind'],
            'recognition' => $recognition,
            'template_id' => is_array($summary['extraction']['template'] ?? null) ? (int) ($summary['extraction']['template']['id'] ?? 0) ?: null : null,
            'summary' => $summary,
            ...\App\Models\Invoicing\IncomingEInvoice::columnsFromSummary($summary),
        ]);

        $document->audit('document.einvoice_received', [
            'number' => $summary['number'],
            'seller' => $summary['seller'],
            'gross' => $summary['gross'],
            'sha256' => $sha256,
            'source' => $source,
        ]);

        // Posteingangsregeln (MVP-1209): erste passende Regel gewinnt — danach kein Abgleich mehr.
        if ($this->applyRules($incoming)) {
            return ['incoming' => $incoming, 'document' => $document];
        }
        // Gegenpartei nur bei eindeutigem, exaktem Treffer (MVP-1108).
        app(IncomingInvoiceMatcher::class)->autoAssign($incoming);

        return ['incoming' => $incoming, 'document' => $document];
    }

    /**
     * Posteingangsregeln (MVP-1209): Automationsregeln zum Auslöser
     * „Rechnungseingang eingegangen“ — verwerfen, ablehnen oder zuordnen.
     * true, sobald eine Regel entschieden hat; dann entfällt der Abgleich.
     */
    public function applyRules(\App\Models\Invoicing\IncomingEInvoice $incoming): bool {
        app(\App\Automation\RuleEngine::class)->dispatch(\App\Services\Invoicing\Automation\IncomingInvoiceReceivedTrigger::KEY, $incoming);
        if (! $incoming->exists) {
            return true;
        }
        $incoming->refresh();

        return $incoming->status === \App\Enums\Invoicing\IncomingEInvoiceStatus::Rejected
            || $incoming->supplier_id !== null
            || $incoming->customer_id !== null;
    }

    /**
     * Beleg endgültig entfernen (MVP-1210): nur Treffer einer Verwerfen-Regel
     * oder abgelehnte Eingänge — nie übergebene, zugeordnete oder gehaltene.
     * Es bleibt die Audit-Zeile mit Prüfsumme, Nummer, Verkäufer, Absender und
     * Grund; Anhänge räumt HasAttachments ab, Übergaben und Einbehalte der FK.
     *
     * @param  'manual'|'rule'|'retention'  $trigger
     */
    public function discard(\App\Models\Invoicing\IncomingEInvoice $incoming, string $reason, ?\App\Models\Platform\User $actor, string $trigger = 'manual'): void {
        if ($incoming->transferred_at !== null || $incoming->supplier_id !== null || $incoming->customer_id !== null) {
            throw new \RuntimeException((string) __('Nur abgelehnte oder nicht zugeordnete Eingänge lassen sich endgültig löschen.'));
        }
        if ($trigger !== 'rule' && $incoming->status !== \App\Enums\Invoicing\IncomingEInvoiceStatus::Rejected) {
            throw new \RuntimeException((string) __('Nur abgelehnte oder nicht zugeordnete Eingänge lassen sich endgültig löschen.'));
        }
        $holds = app(\App\Services\Retention\LegalHoldService::class);
        $holds->assertNotHeld($incoming);
        $document = $incoming->document;
        if ($document !== null) {
            $holds->assertNotHeld($document);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($incoming, $document, $reason, $actor, $trigger): void {
            $incoming->audit('incoming_einvoice.discarded', [
                'sha256' => $incoming->sha256,
                'number' => $incoming->invoice_number,
                'seller' => $incoming->seller_name,
                'sender' => $incoming->sender_email,
                'reason' => $reason,
                'trigger' => $trigger,
                'actor_user_id' => $actor?->id,
            ]);
            $incoming->delete();
            if ($document !== null) {
                app(\App\Services\Document\DocumentService::class)->purge($document, $actor, $reason);
            }
        });
    }

    /**
     * Nacherkennung (MVP-1207): ein Klärfall bleibt einer, bis jemand Werte
     * erfasst — oder bis die Erkennung wiederholt wird, etwa nachdem die
     * Textwerkzeuge des Servers (pdftotext, tesseract, ocrmypdf) nachinstalliert
     * wurden. Erfasste Werte, Übergaben und Ablehnungen bleiben unangetastet.
     */
    public static function isReanalyzable(\App\Models\Invoicing\IncomingEInvoice $incoming): bool {
        return $incoming->recognition === \App\Enums\Invoicing\IncomingInvoiceRecognition::None
            && $incoming->transferred_at === null
            && $incoming->status !== \App\Enums\Invoicing\IncomingEInvoiceStatus::Rejected
            && ! isset(((array) $incoming->summary)['manual']);
    }

    /**
     * Liest das abgelegte Original erneut; true, sobald der Eingang Werte trägt.
     * Prüfsumme, Absender und Eingangszeit bleiben; Titel und Beschreibung des
     * Dokuments ziehen nach, die Gegenpartei wird wie beim Eingang nur bei
     * eindeutigem Treffer gesetzt.
     */
    public function reanalyze(\App\Models\Invoicing\IncomingEInvoice $incoming): bool {
        if (! self::isReanalyzable($incoming)) {
            return false;
        }
        $document = $incoming->document;
        $version = $document?->currentVersion;
        if ($document === null || $version === null) {
            return false;
        }
        $disk = \Illuminate\Support\Facades\Storage::disk((string) ($version->disk ?: 'local'));
        $path = (string) $version->path;
        if ($path === '' || ! $disk->exists($path)) {
            return false;
        }

        $organization = $incoming->organization;
        $summary = $this->analyzeFor($organization, (string) $disk->get($path), $version->mime !== null ? (string) $version->mime : null, $disk->path($path), (string) $version->original_name);
        if (self::isUnrecognized($summary)) {
            // Weiterhin Klärfall: nur Diagnose und Hinweise nachführen — sie zeigen den aktuellen Werkzeugstand.
            ['summary' => $enriched] = $this->enrich($organization, (int) $incoming->organization_id, $summary);
            $incoming->forceFill(['summary' => [...(array) $incoming->summary, ...$enriched]])->save();

            return false;
        }
        $recognition = \App\Enums\Invoicing\IncomingInvoiceRecognition::from((string) $summary['recognition']);
        ['summary' => $summary, 'classification' => $classification] = $this->enrich($organization, (int) $incoming->organization_id, $summary);

        $document->forceFill($this->documentAttributes($recognition, $summary, (string) $version->original_name))->save();
        $incoming->forceFill([
            'direction' => $classification['direction'],
            'kind' => $classification['kind'],
            'recognition' => $recognition,
            'summary' => $summary,
            ...\App\Models\Invoicing\IncomingEInvoice::columnsFromSummary($summary),
        ])->save();
        $incoming->audit('incoming_einvoice.reanalyzed', [
            'number' => $summary['number'],
            'seller' => $summary['seller'],
            'gross' => $summary['gross'],
            'recognition' => $recognition->value,
        ]);
        if (! $this->applyRules($incoming)) {
            app(IncomingInvoiceMatcher::class)->autoAssign($incoming);
        }

        return true;
    }

    /**
     * Richtung, Belegart, Vorschläge, Abweichungen und Tor-Flags — beim Eingang
     * wie bei der Nacherkennung dieselbe Anreicherung.
     *
     * @param  array<string, mixed>  $summary
     * @return array{summary: array<string, mixed>, classification: array{direction: \App\Enums\Billing\DocumentDirection, kind: \App\Enums\Billing\DocumentKind}}
     */
    private function enrich(?\App\Models\Platform\Organization $organization, int $organizationId, array $summary): array {
        $classification = $this->classify($organization, $summary);
        $summary['direction'] = $classification['direction']->value;
        $summary['kind'] = $classification['kind']->value;
        // Zuordnungs-VORSCHLÄGE + Abweichungen (MVP-167): nur Hinweise für
        // den Prüfer — es entsteht NIE automatisch ein Stammdatensatz.
        $summary['suggestions'] = $this->suggestions($organizationId, $summary);
        $addressDeviations = $this->addressDeviations($organization, $summary);
        $summary['deviations'] = [...$this->deviations($summary), ...$addressDeviations];
        $summary['notices'] = $this->notices($summary);
        // Maschinenlesbar für das Übergabe-Tor (MVP-1111); die Meldungstexte sind nur für Menschen.
        $summary['flags'] = array_values(array_filter([
            $addressDeviations === [] ? null : ($classification['direction'] === \App\Enums\Billing\DocumentDirection::Outgoing ? 'own_invoice_copy' : 'not_addressed'),
            self::totalsMismatch($summary) ? 'totals_mismatch' : null,
        ]));

        return ['summary' => $summary, 'classification' => $classification];
    }

    /**
     * Titel und Beschreibung des Dokuments aus der Zusammenfassung.
     *
     * @param  array<string, mixed>  $summary
     * @return array{title: string, description: string}
     */
    private function documentAttributes(\App\Enums\Invoicing\IncomingInvoiceRecognition $recognition, array $summary, string $fileLabel): array {
        return [
            'title' => match ($recognition) {
                \App\Enums\Invoicing\IncomingInvoiceRecognition::Structured => (string) __('E-Rechnung :number — :seller', ['number' => $summary['number'], 'seller' => $summary['seller'] ?? '—']),
                \App\Enums\Invoicing\IncomingInvoiceRecognition::Extracted => (string) __('Eingangsrechnung :number — :seller', ['number' => $summary['number'], 'seller' => $summary['seller'] ?? '—']),
                \App\Enums\Invoicing\IncomingInvoiceRecognition::None => (string) __('Rechnungseingang ohne erkannte Daten — :file', ['file' => $fileLabel]),
            },
            'description' => $recognition === \App\Enums\Invoicing\IncomingInvoiceRecognition::None
                ? (string) __('Nicht erkannt — Werte am Original prüfen und erfassen.')
                : (string) __(':profile · :gross :currency, fällig :due', [
                    'profile' => $summary['profile'],
                    'gross' => NumberHelper::toGermanFormat((float) $summary['gross'], 2, withThousandsSeparator: true),
                    'currency' => $summary['currency'],
                    'due' => $summary['due_date'] ?? '—',
                ]),
        ];
    }

    /**
     * Richtung und Belegart (MVP-1107). Ausgang nur bei einem positiven Treffer
     * auf eine eigene Kennung des Verkäufers (USt-IdNr., Steuernummer, IBAN);
     * ohne bekannte eigene Kennung bleibt es beim Eingang.
     *
     * @param  array<string, mixed>  $summary
     * @return array{direction: \App\Enums\Billing\DocumentDirection, kind: \App\Enums\Billing\DocumentKind}
     */
    public function classify(?\App\Models\Platform\Organization $organization, array $summary): array {
        $own = $this->ownIdentity($organization);
        $sellerVat = self::normalizedVat($summary['seller_vat'] ?? null);
        $sellerTax = self::digitsOf($summary['seller_tax_number'] ?? null);
        $sellerIban = \CommonToolkit\Helper\Data\BankHelper::normalizeIBAN(is_string($summary['creditor_iban'] ?? null) ? $summary['creditor_iban'] : null);

        $isOwnSeller = ($sellerVat !== '' && $sellerVat === $own['vat'])
            || ($sellerTax !== '' && $sellerTax === $own['tax_number'])
            || ($sellerIban !== null && in_array($sellerIban, $own['ibans'], true));

        // Belegart aus dem Text (MVP-1216): Mahnung, Lieferschein, Bestätigung, Angebot — nur für unstrukturierte Belege.
        $hinted = match ((string) ($summary['kind_hint'] ?? '')) {
            'reminder' => \App\Enums\Billing\DocumentKind::Reminder,
            'delivery_note' => \App\Enums\Billing\DocumentKind::DeliveryNote,
            'order_confirmation' => \App\Enums\Billing\DocumentKind::OrderConfirmation,
            'quote' => \App\Enums\Billing\DocumentKind::Quote,
            default => null,
        };
        $kind = match ((string) ($summary['type_code'] ?? '')) {
            '381' => \App\Enums\Billing\DocumentKind::CreditNote,
            '386' => \App\Enums\Billing\DocumentKind::DownPayment,
            default => $hinted ?? (is_numeric($summary['gross'] ?? null) && (float) $summary['gross'] < 0
                ? \App\Enums\Billing\DocumentKind::CreditNote
                : \App\Enums\Billing\DocumentKind::Invoice),
        };

        return [
            'direction' => $isOwnSeller ? \App\Enums\Billing\DocumentDirection::Outgoing : \App\Enums\Billing\DocumentDirection::Incoming,
            'kind' => $kind,
        ];
    }

    /**
     * Eigene Kennungen der Organisation. Sie zählen nie als Treffer für eine
     * fremde Partei (verschmutzte Lexoffice-Kontakte trugen die eigene USt-IdNr.).
     *
     * @return array{vat: string, tax_number: string, ibans: list<string>}
     */
    public function ownIdentity(?\App\Models\Platform\Organization $organization): array {
        if ($organization === null) {
            return ['vat' => '', 'tax_number' => '', 'ibans' => []];
        }
        $seller = app(XRechnungGenerator::class)->sellerDataFor($organization);
        $ibans = array_values(array_filter([
            \CommonToolkit\Helper\Data\BankHelper::normalizeIBAN($seller['iban'] !== '' ? $seller['iban'] : null),
            ...\App\Models\Finance\BankAccount::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)->get(['iban'])
                ->map(static fn (\App\Models\Finance\BankAccount $account): ?string => \CommonToolkit\Helper\Data\BankHelper::normalizeIBAN($account->iban))
                ->all(),
        ]));

        return [
            'vat' => self::normalizedVat($seller['vat_id']),
            'tax_number' => self::digitsOf($seller['tax_number']),
            'ibans' => array_values(array_unique($ibans)),
        ];
    }

    /** @return array{street: ?string, zip: ?string, city: ?string, country: ?string} */
    private static function addressOf(\ERechnungToolkit\Entities\Party $party): array {
        $address = $party->getPostalAddress();
        $street = trim(implode(' ', array_filter([$address?->getStreetName(), $address?->getBuildingNumber()])));

        return [
            'street' => $street !== '' ? $street : null,
            'zip' => $address?->getPostalCode(),
            'city' => $address?->getCity(),
            'country' => $address?->getCountryCode(),
        ];
    }

    /**
     * Netto + Steuer ≠ Brutto (auf einen halben Cent).
     *
     * @param  array<string, mixed>  $summary
     */
    private static function totalsMismatch(array $summary): bool {
        $net = $summary['net'] ?? null;
        $tax = $summary['tax'] ?? null;
        $gross = $summary['gross'] ?? null;

        return is_numeric($net) && is_numeric($tax) && is_numeric($gross) && abs(((float) $net + (float) $tax) - (float) $gross) > 0.005;
    }

    /** USt-IdNr. in Vergleichsform; leer, wenn keine angegeben ist. */
    public static function normalizedVat(mixed $value): string {
        return is_string($value) && trim($value) !== '' ? \CommonToolkit\Helper\Data\VatNumberHelper::normalize($value) : '';
    }

    /** Nur die Ziffern (Steuernummern werden unterschiedlich gegliedert). */
    public static function digitsOf(mixed $value): string {
        return is_string($value) ? (string) preg_replace('/\D+/', '', $value) : '';
    }

    /**
     * Eingangsbeleg, dessen Käufer eine fremde USt-IdNr. trägt: nicht an uns
     * adressiert (Irrläufer, Privat- oder Fremdrechnung). Nur bei bekannten
     * Kennungen auf beiden Seiten — ein Namensvergleich wäre zu ungenau.
     * Ausgangsbeleg mit der Nummer einer eigenen Rechnung: nur eine Kopie.
     *
     * @param  array<string, mixed>  $summary
     * @return list<string>
     */
    private function addressDeviations(?\App\Models\Platform\Organization $organization, array $summary): array {
        if (($summary['direction'] ?? null) === \App\Enums\Billing\DocumentDirection::Outgoing->value) {
            $number = trim((string) ($summary['number'] ?? ''));
            $isOwnInvoice = $organization !== null && $number !== '' && \App\Models\Invoicing\Invoice::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)->where('number', $number)->exists();

            return $isOwnInvoice ? [(string) __('Kopie der eigenen Rechnung :number — die Rechnung ist bereits erfasst.', ['number' => $number])] : [];
        }
        $own = $this->ownIdentity($organization)['vat'];
        $buyer = self::normalizedVat($summary['buyer_vat'] ?? null);

        return $own !== '' && $buyer !== '' && $own !== $buyer
            ? [(string) __('Nicht an uns adressiert: Der Käufer trägt die USt-IdNr. :vat.', ['vat' => $summary['buyer_vat']])]
            : [];
    }

    /**
     * Informative Hinweise ohne Sperrwirkung (MVP-1107).
     *
     * @param  array<string, mixed>  $summary
     * @return list<string>
     */
    private function notices(array $summary): array {
        $sellerVat = self::normalizedVat($summary['seller_vat'] ?? null);
        $isDomestic = $sellerVat === '' || str_starts_with($sellerVat, 'DE');
        $gross = is_numeric($summary['gross'] ?? null) ? (float) $summary['gross'] : 0.0;

        // Kleinbetragsrechnungen bis 250 € brauchen keine E-Rechnung.
        if (($summary['recognition'] ?? null) === \App\Enums\Invoicing\IncomingInvoiceRecognition::Extracted->value
            && ($summary['direction'] ?? null) === \App\Enums\Billing\DocumentDirection::Incoming->value
            && $isDomestic && $gross > 250.0) {
            return [(string) __('Keine E-Rechnung (PDF bzw. Bild). Im inländischen B2B-Verkehr ist das nur übergangsweise zulässig: bis Ende 2026, für Aussteller mit höchstens 800.000 € Vorjahresumsatz bis Ende 2027.')];
        }

        return [];
    }

    /** @return array<string, mixed> Klärfall ohne erkannte Werte */
    private function unrecognizedSummary(): array {
        return [
            'number' => null, 'issue_date' => null, 'due_date' => null,
            'seller' => null, 'seller_vat' => null, 'buyer' => null, 'buyer_vat' => null,
            'currency' => null, 'net' => null, 'tax' => null, 'gross' => null,
            'profile' => (string) __('Nicht erkannt'), 'profile_legacy' => false, 'lines' => 0, 'type_code' => null,
            'order_reference' => null, 'buyer_reference' => null, 'project_reference' => null,
            'creditor_iban' => null, 'creditor_bic' => null, 'discount_percent' => null, 'discount_days' => null,
            'validation' => null,
            'recognition' => \App\Enums\Invoicing\IncomingInvoiceRecognition::None->value,
        ];
    }

    /**
     * Lieferanten-/Bestell-/Projektvorschläge (MVP-167) — reine Kandidaten
     * mit Begründung, sortiert nach Stärke; Übernahme bleibt beim Prüfer.
     *
     * @param  array<string, mixed>  $summary
     * @return array{suppliers: list<array{id: int, label: string, reasons: list<string>}>, customers: list<array{id: int, label: string, reasons: list<string>}>, purchase_orders: list<array{id: int, label: string, reasons: list<string>}>, projects: list<array{id: int, label: string, reasons: list<string>}>}
     */
    public function suggestions(int $organizationId, array $summary): array {
        // Ausgangsbelege (MVP-1107) suchen den Kunden über die Käuferangaben.
        if (($summary['direction'] ?? null) === \App\Enums\Billing\DocumentDirection::Outgoing->value) {
            return ['suppliers' => [], 'customers' => $this->customerSuggestions($organizationId, $summary), 'purchase_orders' => [], 'projects' => []];
        }

        $suppliers = [];
        $sellerVat = trim((string) ($summary['seller_vat'] ?? ''));
        $sellerName = trim((string) ($summary['seller'] ?? ''));

        if ($sellerVat !== '') {
            foreach (\App\Models\Supplier\Supplier::query()->withoutGlobalScopes()->withoutCollective()->where('organization_id', $organizationId)->where('vat_id', $sellerVat)->limit(3)->get() as $supplier) {
                $suppliers[$supplier->id] = ['id' => (int) $supplier->id, 'label' => (string) ($supplier->displayLabel()), 'reasons' => [(string) __('USt-IdNr. stimmt überein')]];
            }
        }
        if ($sellerName !== '') {
            $query = \App\Models\Supplier\Supplier::query()->withoutGlobalScopes()->withoutCollective()->where('organization_id', $organizationId)
                ->where(function ($q) use ($sellerName): void {
                    $q->whereLikeEscaped('name', $sellerName)->orWhereLikeEscaped('company', $sellerName);
                })->limit(3);
            foreach ($query->get() as $supplier) {
                if (isset($suppliers[$supplier->id])) {
                    $suppliers[$supplier->id]['reasons'][] = (string) __('Name ähnlich');
                } else {
                    $suppliers[$supplier->id] = ['id' => (int) $supplier->id, 'label' => (string) ($supplier->displayLabel()), 'reasons' => [(string) __('Name ähnlich')]];
                }
            }
        }

        $purchaseOrders = [];
        $orderRef = trim((string) ($summary['order_reference'] ?? ''));
        if ($orderRef !== '') {
            foreach (\App\Models\Procurement\PurchaseOrder::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('number', $orderRef)->limit(3)->get() as $po) {
                $purchaseOrders[] = ['id' => (int) $po->id, 'label' => (string) $po->number, 'reasons' => [(string) __('Bestellreferenz stimmt überein')]];
            }
        }

        // MVP-1066: Bestell- und Projektnummern, die im Belegtext vorkommen.
        $tokens = array_values(array_filter((array) ($summary['reference_tokens'] ?? []), 'is_string'));
        if ($tokens !== []) {
            foreach (\App\Models\Procurement\PurchaseOrder::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->whereIn('number', $tokens)->limit(3)->get() as $po) {
                if (! in_array((int) $po->id, array_column($purchaseOrders, 'id'), true)) {
                    $purchaseOrders[] = ['id' => (int) $po->id, 'label' => (string) $po->number, 'reasons' => [(string) __('Bestellnummer im Belegtext')]];
                }
            }
        }

        $projects = [];
        if ($tokens !== []) {
            foreach (\App\Models\Project\Project::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->whereIn('number', $tokens)->limit(3)->get() as $project) {
                $projects[] = ['id' => (int) $project->id, 'label' => (string) $project->name, 'reasons' => [(string) __('Projektnummer im Belegtext')]];
            }
        }
        $projectRef = trim((string) ($summary['project_reference'] ?? ($summary['buyer_reference'] ?? '')));
        if ($projectRef !== '') {
            foreach (\App\Models\Project\Project::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->whereLikeEscaped('name', $projectRef)->limit(3)->get() as $project) {
                $projects[] = ['id' => (int) $project->id, 'label' => (string) $project->name, 'reasons' => [(string) __('Projektreferenz ähnlich')]];
            }
        }

        return [
            'suppliers' => array_values($suppliers),
            'customers' => [],
            'purchase_orders' => $purchaseOrders,
            'projects' => $projects,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<array{id: int, label: string, reasons: list<string>}>
     */
    private function customerSuggestions(int $organizationId, array $summary): array {
        $customers = [];
        $buyerVat = self::normalizedVat($summary['buyer_vat'] ?? null);
        if ($buyerVat !== '') {
            foreach (\App\Models\Customer\Customer::query()->withoutGlobalScopes()->withoutCollective()->where('organization_id', $organizationId)->whereNotNull('vat_id')->get(['id', 'name', 'vat_id']) as $customer) {
                if (self::normalizedVat($customer->vat_id) === $buyerVat) {
                    $customers[$customer->id] = ['id' => (int) $customer->id, 'label' => (string) $customer->name, 'reasons' => [(string) __('USt-IdNr. stimmt überein')]];
                }
            }
        }
        $buyerName = trim((string) ($summary['buyer'] ?? ''));
        if ($buyerName !== '') {
            foreach (\App\Models\Customer\Customer::query()->withoutGlobalScopes()->withoutCollective()->where('organization_id', $organizationId)->whereLikeEscaped('name', $buyerName)->limit(3)->get(['id', 'name']) as $customer) {
                $customers[$customer->id] ??= ['id' => (int) $customer->id, 'label' => (string) $customer->name, 'reasons' => []];
                $customers[$customer->id]['reasons'][] = (string) __('Name ähnlich');
            }
        }

        return array_values($customers);
    }

    /**
     * Abweichungsprüfung (MVP-167): Summen-Inkonsistenz und fehlende
     * Steuerkennung werden sichtbar eskaliert — nie stillschweigend
     * verarbeitet. Doppelte Rechnungsnummern prüft der Eingang selbst und
     * sperrt die Übergabe (MVP-1145, {@see \App\Models\Invoicing\IncomingEInvoice::refreshDuplicate()}).
     *
     * @param  array<string, mixed>  $summary
     * @return list<string>
     */
    public function deviations(array $summary): array {
        $deviations = [];
        if (self::isUnrecognized($summary)) {
            $deviations[] = (string) __('Keine Rechnungsdaten erkannt — Original prüfen und die Werte erfassen.');
            $hint = self::recognitionHint((array) ($summary['extraction'] ?? []));
            if ($hint !== null) {
                $deviations[] = $hint;
            }
        }
        if ($summary['unstructured'] ?? false) {
            $deviations[] = (string) __('Ohne E-Rechnungsdaten aus PDF bzw. Bild erkannt — alle Werte am Original prüfen.');
        }
        if ($summary['profile_legacy'] ?? false) {
            $deviations[] = (string) __('Altformat ZUGFeRD 1.0: keine E-Rechnung nach § 14 UStG. Die Werte stammen aus der eingebetteten XML; eine Regelprüfung nach EN 16931 ist für dieses Format nicht möglich.');
        }

        $net = $summary['net'] ?? null;
        $tax = $summary['tax'] ?? null;
        $gross = $summary['gross'] ?? null;
        if (self::totalsMismatch($summary)) {
            $deviations[] = (string) __('Summen widersprüchlich: Netto + Steuer ≠ Brutto (:net + :tax ≠ :gross).', [
                'net' => NumberHelper::toGermanFormat((float) $net, 2, withThousandsSeparator: true),
                'tax' => NumberHelper::toGermanFormat((float) $tax, 2, withThousandsSeparator: true),
                'gross' => NumberHelper::toGermanFormat((float) $gross, 2, withThousandsSeparator: true),
            ]);
        }

        if ((float) ($tax ?? 0) > 0.0 && trim((string) ($summary['seller_vat'] ?? '')) === '') {
            $deviations[] = (string) __('Steuerausweis ohne USt-IdNr./Steuernummer des Ausstellers.');
        }

        return $deviations;
    }

    /**
     * Rechnung ohne eingebettete XML (MVP-1066): die vorhandene Erkennung aus
     * Text, Tabellen, OCR und KI-Rückfall ({@see \App\Services\Invoicing\InvoicePdfImportService}).
     * Ohne erkannte Rechnungsnummer und Bruttobetrag ist es keine Rechnung —
     * dann bleibt `summary` null und `extraction` sagt, was gelesen wurde und
     * woran es scheiterte. Der Aussteller wird über die USt-IdNr. im Text
     * gesucht; die eigene zählt nicht.
     *
     * @return array{summary: array<string, mixed>|null, extraction: array<string, mixed>}
     */
    private function unstructuredSummary(?\App\Models\Platform\Organization $organization, string $contents, ?string $mime, ?string $path, ?string $originalName): array {
        $extension = match (true) {
            str_contains((string) $mime, 'pdf') || str_ends_with(mb_strtolower((string) $originalName), '.pdf') => 'pdf',
            str_contains((string) $mime, 'png') => 'png',
            str_contains((string) $mime, 'jpeg') || str_contains((string) $mime, 'jpg') => 'jpg',
            str_contains((string) $mime, 'tif') => 'tif',
            default => null,
        };
        if ($extension === null) {
            return ['summary' => null, 'extraction' => self::recognitionDiagnostics('format_not_supported', [], $mime ?? $originalName)];
        }
        if ($organization === null) {
            return ['summary' => null, 'extraction' => self::recognitionDiagnostics('not_attempted', [], $extension)];
        }
        // Der E-Rechnungs-Zweig lief schon in analyzeFor(): hier nur noch Text, Tabellen, OCR, KI.
        $extract = static fn (string $file): array => app(\App\Services\Invoicing\InvoicePdfImportService::class)->extract($file, $extension, $mime, $organization, structured: false);
        try {
            $result = $path !== null && File::isFile($path) ? $extract($path) : File::withTemp($contents, $extract, 'incoming-invoice-', $extension);
        } catch (FileNotWrittenException) {
            return ['summary' => null, 'extraction' => self::recognitionDiagnostics('not_attempted', [], $extension)];
        }
        if (($result['number'] ?? null) === null || ($result['gross'] ?? null) === null) {
            $reason = match (true) {
                (int) ($result['text_length'] ?? 0) === 0 => 'no_text',
                ($result['number'] ?? null) === null && ($result['gross'] ?? null) === null => 'no_number_and_gross',
                ($result['number'] ?? null) === null => 'no_number',
                default => 'no_gross',
            };

            return ['summary' => null, 'extraction' => self::recognitionDiagnostics($reason, $result, $extension)];
        }

        $ownVat = \CommonToolkit\Helper\Data\VatNumberHelper::normalize(app(XRechnungGenerator::class)->sellerDataFor($organization)['vat_id']);
        $vatIds = array_values(array_filter(
            [...(array) ($result['vat_ids'] ?? []), ...(isset($result['seller_vat']) ? [(string) $result['seller_vat']] : [])],
            static fn (string $vat): bool => $vat !== '' && $vat !== $ownVat,
        ));
        $supplier = $vatIds === [] ? null : \App\Models\Supplier\Supplier::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)->whereIn('vat_id', $vatIds)->first();

        return ['extraction' => [], 'summary' => [
            'number' => $result['number'],
            'issue_date' => $result['issued_on'] ?? null,
            'due_date' => $result['due_on'] ?? null,
            'seller' => $supplier?->displayLabel(),
            'seller_vat' => $supplier->vat_id ?? ($vatIds[0] ?? null),
            'currency' => $result['currency'] ?? 'EUR',
            'net' => $result['net'] ?? null,
            'tax' => $result['tax'] ?? null,
            'gross' => $result['gross'],
            'profile' => (string) __('PDF/Bild (erkannt)'),
            'profile_legacy' => false,
            'lines' => count((array) ($result['lines'] ?? [])),
            'order_reference' => null,
            'buyer_reference' => $result['buyer_reference'] ?? null,
            'project_reference' => null,
            'creditor_iban' => $result['payment']['iban'] ?? null,
            'creditor_bic' => $result['payment']['bic'] ?? null,
            'discount_percent' => $result['skonto']['percent'] ?? null,
            'discount_days' => $result['skonto']['days'] ?? null,
            'unstructured' => true,
            'kind_hint' => $result['kind_hint'] ?? null,
            'reminder_for' => array_values((array) ($result['reminder_numbers'] ?? [])),
            'extraction' => [
                'reader' => $result['reader'] ?? null,
                'ocr_used' => (bool) ($result['ocr_used'] ?? false),
                'confidence' => $result['confidence'] ?? null,
                'warnings' => (array) ($result['warnings'] ?? []),
                'sources' => (array) ($result['sources'] ?? []),
                'hints' => array_filter(['issue_date' => $result['issued_on_hint'] ?? null]),
                'template' => $result['template'] ?? null,
            ],
            'reference_tokens' => array_values((array) ($result['reference_tokens'] ?? [])),
        ]];
    }

    /**
     * Diagnose einer gescheiterten Erkennung: Leseweg, Textmenge, Warnungen
     * und die Felder, die trotzdem gefunden wurden (sie belegen den Dialog
     * „Werte erfassen“ vor).
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private static function recognitionDiagnostics(string $reason, array $result, ?string $format): array {
        $found = array_filter(
            [
                'number' => $result['number'] ?? null,
                'issued_on' => $result['issued_on'] ?? null,
                'due_on' => $result['due_on'] ?? null,
                'net' => $result['net'] ?? null,
                'tax' => $result['tax'] ?? null,
                'gross' => $result['gross'] ?? null,
            ],
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        );

        return [
            'reason' => $reason,
            'reader' => $result['reader'] ?? null,
            'ocr_used' => (bool) ($result['ocr_used'] ?? false),
            'text_length' => (int) ($result['text_length'] ?? 0),
            'confidence' => $result['confidence'] ?? null,
            'warnings' => array_values(array_filter((array) ($result['warnings'] ?? []), 'is_string')),
            'source_format' => $format,
            'found' => $found,
            'sources' => (array) ($result['sources'] ?? []),
            'hints' => array_filter(['issue_date' => $result['issued_on_hint'] ?? null]),
            'kind_hint' => $result['kind_hint'] ?? null,
            'reminder_for' => array_values((array) ($result['reminder_numbers'] ?? [])),
        ];
    }

    /**
     * Lesbarer Grund für den Prüfer, abgeleitet aus {@see recognitionDiagnostics()}.
     *
     * @param  array<string, mixed>  $extraction
     */
    private static function recognitionHint(array $extraction): ?string {
        $source = (($extraction['ocr_used'] ?? false) ? 'OCR/' : '') . (string) ($extraction['reader'] ?? '?');
        $read = ['length' => (int) ($extraction['text_length'] ?? 0), 'source' => $source];

        return match ($extraction['reason'] ?? null) {
            'format_not_supported' => (string) __('Dateityp :type wird nicht als Beleg gelesen — erkannt werden PDF, Bilder und E-Rechnungs-XML.', ['type' => (string) ($extraction['source_format'] ?? '?')]),
            'no_text' => ($extraction['ocr_used'] ?? false)
                ? (string) __('Kein lesbarer Text, auch nicht per OCR — vermutlich kein Beleg oder eine unbrauchbare Vorlage.')
                : (string) __('Kein Text in der Datei gefunden — Textschicht fehlt und OCR war nicht verfügbar.'),
            'no_number' => (string) __('Text gelesen (:length Zeichen, :source), aber keine Rechnungsnummer bei einer bekannten Beschriftung gefunden.', $read),
            'no_gross' => (string) __('Text gelesen (:length Zeichen, :source), aber kein Bruttobetrag bei einer bekannten Beschriftung gefunden.', $read),
            'no_number_and_gross' => (string) __('Text gelesen (:length Zeichen, :source), aber weder Rechnungsnummer noch Bruttobetrag bei einer bekannten Beschriftung gefunden.', $read),
            default => null,
        };
    }

    private function parsePdf(string $contents, ?string $path): ?EInvoiceDocument {
        $parser = new ZugferdPdfParser;
        try {
            // Mit bekanntem Pfad direkt, sonst puffert das Toolkit die Bytes selbst.
            if ($path !== null && File::isFile($path)) {
                return $parser->isAvailable() && $parser->isZugferdPdf($path) ? $parser->parseFile($path) : null;
            }

            return $parser->parseString($contents);
        } catch (Throwable) {
            return null;
        }
    }
}
