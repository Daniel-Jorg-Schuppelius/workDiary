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

use CommonToolkit\Helper\Data\{CryptoHelper, XmlHelper};
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\Helper\FileSystem\File;
use ERechnungToolkit\Entities\Document as EInvoiceDocument;
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
    /**
     * Versucht, Dateiinhalt als E-Rechnung zu parsen. `null`, wenn es keine
     * (lesbare) E-Rechnung ist — Aufrufer behandeln das als „normale Datei".
     */
    public function parse(string $contents, ?string $mime = null, ?string $path = null): ?EInvoiceDocument {
        $isPdf = str_starts_with($contents, '%PDF')
            || ($mime !== null && str_contains($mime, 'pdf'));

        if ($isPdf) {
            return $this->parsePdf($contents, $path);
        }

        if (! str_contains(substr($contents, 0, 512), '<')) {
            return null; // offensichtlich kein XML
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
        $isPdf = str_starts_with($contents, '%PDF') || ($mime !== null && str_contains($mime, 'pdf'));
        if (! $isPdf) {
            return str_contains(substr($contents, 0, 512), '<') ? $contents : null;
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
     * Eingangs-Validierung (MVP-166): XSD der tatsächlichen Syntax (UBL oder
     * CII, gewählt am Wurzelelement) + KoSIT (XSD/EN-16931/CIUS).
     * Verfügbarkeit wird transparent ausgewiesen.
     *
     * @return array{schema_checked: bool, schema_errors: array<int, string>, kosit_available: bool, kosit_valid: bool|null, kosit_errors: array<int, string>}
     */
    public function validateXml(string $xml): array {
        $result = [
            'schema_checked' => false,
            'schema_errors' => [],
            'kosit_available' => false,
            'kosit_valid' => null,
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

        $kosit = new \ERechnungToolkit\Validators\KositValidator;
        $result['kosit_available'] = $kosit->isAvailable();
        if ($result['kosit_available']) {
            $report = $kosit->validate($xml);
            $result['kosit_valid'] = $report->isAccepted();
            $result['kosit_errors'] = array_map('strval', $report->getErrors());
        }

        return $result;
    }

    /**
     * Kernfelder für Anzeige/Flash — die Detailseite parst das Original
     * bei jedem Aufruf erneut (kein eigenes Schema, Quelle bleibt die Datei).
     *
     * @return array{number: string, issue_date: ?string, due_date: ?string, seller: ?string, seller_vat: ?string, currency: string, net: string, tax: string, gross: string, profile: string, lines: int, order_reference: ?string, buyer_reference: ?string, project_reference: ?string, creditor_iban: ?string, creditor_bic: ?string, discount_percent: ?float, discount_days: ?int}
     */
    public function summary(EInvoiceDocument $document): array {
        return [
            'number' => $document->getId(),
            'issue_date' => $document->getIssueDate()->format('Y-m-d'),
            'due_date' => $document->getDueDate()?->format('Y-m-d'),
            'seller' => $document->getSeller()->getName(),
            'seller_vat' => $document->getSeller()->getVatId(),
            'currency' => $document->getCurrency()->value,
            'net' => $document->getNetAmount()->getAmount(),
            'tax' => $document->getTaxAmount()->getAmount(),
            'gross' => $document->getGrossAmount()->getAmount(),
            'profile' => $document->getProfile()->label(),
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
     * Zentrale Eingangsverarbeitung ALLER Kanäle (MVP-165/167): Hash-Dedup
     * je Organisation, Parse, Validierung, Vorschläge/Abweichungen, Ablage
     * als Document (DMS) + Prüfbereich-Datensatz. Kanäle unterscheiden sich
     * nur in der `source`-Herkunft — nie in der Verarbeitung.
     *
     * @return array{status: 'created'|'duplicate'|'unreadable', incoming: \App\Models\Invoicing\IncomingEInvoice|null, document: \App\Models\Document\Document|null}
     */
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
     * @return array{status: string, incoming: ?\App\Models\Invoicing\IncomingEInvoice, document: ?\App\Models\Document\Document}
     */
    public function storeIncoming(
        \App\Models\Platform\User $actor,
        string $contents,
        ?string $mime = null,
        ?string $path = null,
        string $source = 'upload',
        ?\Illuminate\Http\UploadedFile $file = null,
        ?string $originalName = null,
    ): array {
        $organizationId = (int) $actor->organization_id;
        $sha256 = CryptoHelper::hash($contents);

        // Inhaltsbasierter Dedup (MVP-165): identische Datei je Org genau einmal —
        // auch kanalübergreifend (Upload nach Mail bleibt Dublette).
        $duplicate = \App\Models\Invoicing\IncomingEInvoice::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('sha256', $sha256)
            ->first();
        if ($duplicate !== null) {
            return ['status' => 'duplicate', 'incoming' => $duplicate, 'document' => null];
        }

        // Dateisicherheitsprüfung (Feature 066, Eingangsverarbeitung Schritt 3):
        // nach Hash/Dublette, vor dem Parsen. Alle fünf Kanäle (Upload, Mail,
        // Peppol, Cloud-Eingang, PDF-Import) laufen hier durch, deshalb sitzt
        // die Prüfung im Dienst und nicht in einem Controller.
        if ($this->isInfected($contents, $mime, $path, $file)) {
            return ['status' => 'infected', 'incoming' => null, 'document' => null];
        }

        $parsed = $this->parse($contents, $mime, $path);
        if ($parsed === null) {
            return ['status' => 'unreadable', 'incoming' => null, 'document' => null];
        }

        $summary = $this->summary($parsed);

        // Eingangs-Validierung (MVP-166): getrennt vom Original abgelegt.
        $extractedXml = $this->extractXml($contents, $mime, $path);
        $summary['validation'] = $extractedXml !== null ? $this->validateXml($extractedXml) : null;

        // Zuordnungs-VORSCHLÄGE + Abweichungen (MVP-167): nur Hinweise für
        // den Prüfer — es entsteht NIE automatisch ein Stammdatensatz.
        $summary['suggestions'] = $this->suggestions($organizationId, $summary);
        $summary['deviations'] = $this->deviations($organizationId, $summary);

        $attributes = [
            'title' => (string) __('E-Rechnung :number — :seller', [
                'number' => $summary['number'],
                'seller' => $summary['seller'] ?? '—',
            ]),
            'document_type' => \App\Enums\Document\DocumentType::Invoice->value,
            'description' => (string) __(':profile · :gross :currency, fällig :due', [
                'profile' => $summary['profile'],
                'gross' => NumberHelper::toGermanFormat($summary['gross'], 2, withThousandsSeparator: true),
                'currency' => $summary['currency'],
                'due' => $summary['due_date'] ?? '—',
            ]),
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
            'received_at' => now(),
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

        return ['status' => 'created', 'incoming' => $incoming, 'document' => $document];
    }

    /**
     * Lieferanten-/Bestell-/Projektvorschläge (MVP-167) — reine Kandidaten
     * mit Begründung, sortiert nach Stärke; Übernahme bleibt beim Prüfer.
     *
     * @param  array<string, mixed>  $summary
     * @return array{suppliers: list<array{id: int, label: string, reasons: list<string>}>, purchase_orders: list<array{id: int, label: string, reasons: list<string>}>, projects: list<array{id: int, label: string, reasons: list<string>}>}
     */
    public function suggestions(int $organizationId, array $summary): array {
        $suppliers = [];
        $sellerVat = trim((string) ($summary['seller_vat'] ?? ''));
        $sellerName = trim((string) ($summary['seller'] ?? ''));

        if ($sellerVat !== '') {
            foreach (\App\Models\Supplier\Supplier::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('vat_id', $sellerVat)->limit(3)->get() as $supplier) {
                $suppliers[$supplier->id] = ['id' => (int) $supplier->id, 'label' => (string) ($supplier->displayLabel()), 'reasons' => [(string) __('USt-IdNr. stimmt überein')]];
            }
        }
        if ($sellerName !== '') {
            $query = \App\Models\Supplier\Supplier::query()->withoutGlobalScopes()->where('organization_id', $organizationId)
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

        $projects = [];
        $projectRef = trim((string) ($summary['project_reference'] ?? ($summary['buyer_reference'] ?? '')));
        if ($projectRef !== '') {
            foreach (\App\Models\Project\Project::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->whereLikeEscaped('name', $projectRef)->limit(3)->get() as $project) {
                $projects[] = ['id' => (int) $project->id, 'label' => (string) $project->name, 'reasons' => [(string) __('Projektreferenz ähnlich')]];
            }
        }

        return [
            'suppliers' => array_values($suppliers),
            'purchase_orders' => $purchaseOrders,
            'projects' => $projects,
        ];
    }

    /**
     * Abweichungsprüfung (MVP-167): doppelte Rechnungsnummern desselben
     * Ausstellers, Summen-Inkonsistenz und fehlende Steuerkennung werden
     * sichtbar eskaliert — nie stillschweigend verarbeitet.
     *
     * @param  array<string, mixed>  $summary
     * @return list<string>
     */
    public function deviations(int $organizationId, array $summary): array {
        $deviations = [];

        $number = trim((string) ($summary['number'] ?? ''));
        if ($number !== '') {
            $sameNumber = \App\Models\Invoicing\IncomingEInvoice::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('summary->number', $number)
                ->when(trim((string) ($summary['seller_vat'] ?? '')) !== '', fn($q) => $q->where('summary->seller_vat', trim((string) $summary['seller_vat'])))
                ->exists();
            if ($sameNumber) {
                $deviations[] = (string) __('Rechnungsnummer :number dieses Ausstellers wurde bereits erfasst (möglicher Doppel-Eingang mit anderem Dateiinhalt).', ['number' => $number]);
            }
        }

        $net = $summary['net'] ?? null;
        $tax = $summary['tax'] ?? null;
        $gross = $summary['gross'] ?? null;
        if ($net !== null && $tax !== null && $gross !== null && abs(((float) $net + (float) $tax) - (float) $gross) > 0.005) {
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
