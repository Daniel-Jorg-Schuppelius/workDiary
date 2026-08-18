<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Invoice;
use App\Services\BrandingService;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

/**
 * Rendert die Druckansicht einer Rechnung (`invoices.pdf`) zu PDF-Bytes über
 * die pdf-toolkit `PDFWriterRegistry`. Geteilt von Controller-Download,
 * Mail-Anhang und der WebDAV-Spiegelung (Rang 19), damit alle exakt dasselbe
 * Dokument erzeugen (gleiche Vorlage + Rechtsangaben).
 *
 * Feature 076: Das HTML läuft durch die Dokumentdesign-Pipeline
 * (Firmenbogen, Druckbereiche, Tabellenstil). Finalisierte Rechnungen
 * verwenden ihren eingefrorenen Render-Snapshot; ohne Profil bleibt die
 * Ausgabe unverändert (Systemfallback).
 */
class InvoicePdfRenderer {
    public function __construct(private readonly DocumentDesignRenderer $design) {}

    /** PDF-Bytes der Rechnung (A4). */
    public function output(Invoice $invoice): string {
        return PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($this->composedHtml($invoice)))
            ?? throw new RuntimeException('PDF-Erzeugung fehlgeschlagen (invoices.pdf).');
    }

    /**
     * Komponiertes Dokument-HTML (auch für den ZUGFeRD-Pfad, der dasselbe
     * sichtbare PDF einbetten muss wie der direkte Download).
     */
    public function composedHtml(Invoice $invoice): string {
        $payload = $this->designPayload($invoice);
        $html = view('invoices.pdf', $this->viewData($invoice, $payload))->render();

        return $this->design->compose($html, $payload);
    }

    /**
     * View-Daten der Druckansicht: Rechtsangaben der Organisation +
     * Design-Kontext (Feature 076; MVP-651: Kopf-/Fußtexte und Akzentfarbe
     * kommen aus dem Design-Payload statt aus invoice_templates).
     *
     * @param array<string, mixed>|null $payload
     * @return array{invoice: Invoice, orgLegal: mixed, design: \App\Services\DocumentDesign\DesignContext}
     */
    public function viewData(Invoice $invoice, ?array $payload = null): array {
        return [
            'invoice' => $invoice,
            // Rechtsangaben aus der Org DER RECHNUNG statt aus dem Ambient-
            // Kontext: im Queue-Worker (Mail-Anhang) gibt es keinen Auth-User —
            // sonst fehlten die §14-UStG-Pflichtangaben im PDF-Footer.
            'orgLegal' => app(BrandingService::class)->legalFor($invoice->organization),
            'design' => $this->design->context($payload ?? $this->designPayload($invoice)),
        ];
    }

    /**
     * Design-Payload der Rechnung: eingefrorener Snapshot (finalisierte
     * Belege) vor aktivem Profil vor Systemfallback (null). Die Render-Art
     * folgt dem Belegtyp (#83: Gutschrift/Pro-forma als eigene Arten);
     * Bestandsbelege sind noch unter `invoice` eingefroren — der
     * Alt-Schlüssel wird mitgeprüft.
     *
     * @return array<string, mixed>|null
     */
    private function designPayload(Invoice $invoice): ?array {
        $kind = RenderDocumentKind::forInvoiceType((string) $invoice->type);

        $snapshot = $this->design->payloadFromSnapshot($invoice, $kind)
            ?? ($kind !== RenderDocumentKind::Invoice
                ? $this->design->payloadFromSnapshot($invoice, RenderDocumentKind::Invoice)
                : null);
        if ($snapshot !== null) {
            return $snapshot;
        }
        // Ohne Snapshot (Entwurf/Bestandsbeleg): aktives Profil der Org.
        if ($invoice->party_snapshot !== null && $this->hasSnapshotRecord($invoice, $kind)) {
            return null; // Snapshot des Systemfallbacks → heutige Ausgabe
        }

        return $invoice->organization === null
            ? null
            : $this->design->payloadFor($invoice->organization, $kind, (int) $invoice->customer_id);
    }

    private function hasSnapshotRecord(Invoice $invoice, RenderDocumentKind $kind): bool {
        return \App\Models\DocumentDesign\DocumentRenderSnapshot::query()
            ->withoutGlobalScopes()
            ->where('documentable_type', $invoice->getMorphClass())
            ->where('documentable_id', $invoice->getKey())
            ->whereIn('document_kind', array_unique([$kind->value, RenderDocumentKind::Invoice->value]))
            ->exists();
    }
}
