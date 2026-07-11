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

use ERechnungToolkit\Entities\Document as EInvoiceDocument;
use ERechnungToolkit\Parsers\{ERechnungParser, ZugferdPdfParser};
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
        if ($path === null) {
            return null;
        }

        try {
            return (new \ERechnungToolkit\Parsers\ZugferdPdfParser)->extractXml($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Eingangs-Validierung (MVP-166): UBL-XSD (nur wenn das Schema das
     * Wurzelelement kennt — CII wird nur von KoSIT geprüft) + KoSIT
     * (XSD/EN-16931/CIUS). Verfügbarkeit wird transparent ausgewiesen.
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

        $schema = new \ERechnungToolkit\Validators\UblSchemaValidator;
        $root = null;
        try {
            $dom = new \DOMDocument;
            if (@$dom->loadXML($xml)) {
                $root = $dom->documentElement?->localName;
            }
        } catch (Throwable) {
        }
        if ($schema->isAvailable() && $root !== null && $schema->supports($root)) {
            $result['schema_checked'] = true;
            $result['schema_errors'] = $schema->validate($xml);
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
     * @return array{number: string, issue_date: ?string, due_date: ?string, seller: ?string, seller_vat: ?string, currency: string, net: ?float, tax: ?float, gross: ?float, profile: string, lines: int, order_reference: ?string, buyer_reference: ?string, project_reference: ?string}
     */
    public function summary(EInvoiceDocument $document): array {
        return [
            'number' => $document->getId(),
            'issue_date' => $document->getIssueDate()->format('Y-m-d'),
            'due_date' => $document->getDueDate()?->format('Y-m-d'),
            'seller' => $document->getSeller()->getName(),
            'seller_vat' => $document->getSeller()->getVatId(),
            'currency' => $document->getCurrency()->value,
            'net' => $document->getNetAmount(),
            'tax' => $document->getTaxAmount(),
            'gross' => $document->getGrossAmount(),
            'profile' => $document->getProfile()->label(),
            'lines' => $document->countLines(),
            'order_reference' => $document->getOrderReference(),
            'buyer_reference' => $document->getBuyerReference(),
            'project_reference' => $document->getProjectReference(),
        ];
    }

    /**
     * Zentrale Eingangsverarbeitung ALLER Kanäle (MVP-165/167): Hash-Dedup
     * je Organisation, Parse, Validierung, Vorschläge/Abweichungen, Ablage
     * als Document (DMS) + Prüfbereich-Datensatz. Kanäle unterscheiden sich
     * nur in der `source`-Herkunft — nie in der Verarbeitung.
     *
     * @return array{status: 'created'|'duplicate'|'unreadable', incoming: \App\Models\IncomingEInvoice|null, document: \App\Models\Document|null}
     */
    public function storeIncoming(
        \App\Models\User $actor,
        string $contents,
        ?string $mime = null,
        ?string $path = null,
        string $source = 'upload',
        ?\Illuminate\Http\UploadedFile $file = null,
        ?string $originalName = null,
    ): array {
        $organizationId = (int) $actor->organization_id;
        $sha256 = hash('sha256', $contents);

        // Inhaltsbasierter Dedup (MVP-165): identische Datei je Org genau einmal —
        // auch kanalübergreifend (Upload nach Mail bleibt Dublette).
        $duplicate = \App\Models\IncomingEInvoice::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('sha256', $sha256)
            ->first();
        if ($duplicate !== null) {
            return ['status' => 'duplicate', 'incoming' => $duplicate, 'document' => null];
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
                'gross' => number_format((float) ($summary['gross'] ?? 0), 2, ',', '.'),
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
            $document = \App\Models\Document::query()->create([
                'organization_id' => $organizationId,
                'title' => $attributes['title'],
                'document_type' => $attributes['document_type'],
                'status' => \App\Enums\Document\DocumentStatus::Active->value,
                'description' => $attributes['description'],
                'created_by_user_id' => $actor->id,
            ]);
            $documents->addVersionFromContents($document, $actor, $contents, $originalName ?? ('e-rechnung-' . $sha256 . ($mime !== null && str_contains($mime, 'pdf') ? '.pdf' : '.xml')), $mime);
        }

        $incoming = \App\Models\IncomingEInvoice::query()->create([
            'organization_id' => $organizationId,
            'document_id' => $document->id,
            'sha256' => $sha256,
            'source' => $source,
            'received_at' => now(),
            'summary' => $summary,
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
            foreach (\App\Models\Supplier::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('vat_id', $sellerVat)->limit(3)->get() as $supplier) {
                $suppliers[$supplier->id] = ['id' => (int) $supplier->id, 'label' => (string) ($supplier->company ?: $supplier->name), 'reasons' => [(string) __('USt-IdNr. stimmt überein')]];
            }
        }
        if ($sellerName !== '') {
            $query = \App\Models\Supplier::query()->withoutGlobalScopes()->where('organization_id', $organizationId)
                ->where(function ($q) use ($sellerName): void {
                    $q->whereLikeEscaped('name', $sellerName)->orWhereLikeEscaped('company', $sellerName);
                })->limit(3);
            foreach ($query->get() as $supplier) {
                if (isset($suppliers[$supplier->id])) {
                    $suppliers[$supplier->id]['reasons'][] = (string) __('Name ähnlich');
                } else {
                    $suppliers[$supplier->id] = ['id' => (int) $supplier->id, 'label' => (string) ($supplier->company ?: $supplier->name), 'reasons' => [(string) __('Name ähnlich')]];
                }
            }
        }

        $purchaseOrders = [];
        $orderRef = trim((string) ($summary['order_reference'] ?? ''));
        if ($orderRef !== '') {
            foreach (\App\Models\PurchaseOrder::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('number', $orderRef)->limit(3)->get() as $po) {
                $purchaseOrders[] = ['id' => (int) $po->id, 'label' => (string) $po->number, 'reasons' => [(string) __('Bestellreferenz stimmt überein')]];
            }
        }

        $projects = [];
        $projectRef = trim((string) ($summary['project_reference'] ?? ($summary['buyer_reference'] ?? '')));
        if ($projectRef !== '') {
            foreach (\App\Models\Project::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->whereLikeEscaped('name', $projectRef)->limit(3)->get() as $project) {
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
            $sameNumber = \App\Models\IncomingEInvoice::query()
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
                'net' => number_format((float) $net, 2, ',', '.'),
                'tax' => number_format((float) $tax, 2, ',', '.'),
                'gross' => number_format((float) $gross, 2, ',', '.'),
            ]);
        }

        if ((float) ($tax ?? 0) > 0.0 && trim((string) ($summary['seller_vat'] ?? '')) === '') {
            $deviations[] = (string) __('Steuerausweis ohne USt-IdNr./Steuernummer des Ausstellers.');
        }

        return $deviations;
    }

    private function parsePdf(string $contents, ?string $path): ?EInvoiceDocument {
        $parser = new ZugferdPdfParser;
        if (! $parser->isAvailable()) {
            return null;
        }

        // Der PDF-Parser arbeitet dateibasiert; ohne bekannten Pfad kurz puffern.
        $tempPath = null;
        if ($path === null || ! is_file($path)) {
            $tempPath = tempnam(sys_get_temp_dir(), 'einvoice-');
            if ($tempPath === false) {
                return null;
            }
            file_put_contents($tempPath, $contents);
            $path = $tempPath;
        }

        try {
            if (! $parser->isZugferdPdf($path)) {
                return null;
            }

            return $parser->parseFile($path);
        } catch (Throwable) {
            return null;
        } finally {
            if ($tempPath !== null) {
                @unlink($tempPath);
            }
        }
    }
}
