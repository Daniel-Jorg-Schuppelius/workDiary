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
use App\Models\Invoicing\Invoice;
use App\Services\DocumentDesign\{DocumentDesignRenderer, IssuerIdentity};
use App\Services\UI\BrandingService;
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
        $invoice->loadMissing(['customer', 'organization']);

        // Belegsprache je Kunde (Feature 034, MVP-721): nur Darstellung.
        return \App\Support\DocumentLocale::within($invoice->customer, $invoice->organization, function () use ($invoice): string {
            $payload = $this->designPayload($invoice);
            $html = view('invoices.pdf', $this->viewData($invoice, $payload))->render();

            return $this->design->compose($html, $payload);
        });
    }

    /**
     * View-Daten der Druckansicht: Rechtsangaben der Organisation +
     * Design-Kontext (Feature 076; MVP-651: Kopf-/Fußtexte und Akzentfarbe
     * kommen aus dem Design-Payload statt aus invoice_templates).
     *
     * @param array<string, mixed>|null $payload
     * @return array{invoice: Invoice, orgLegal: mixed, design: \App\Services\DocumentDesign\DesignContext, taxNotes: list<string>, issuer: array<string, mixed>, contactPerson: array<string, mixed>|null, recipient: array<string, string>, buyerVatId: string|null}
     */
    public function viewData(Invoice $invoice, ?array $payload = null): array {
        $design = $this->design->contextForDocument(
            $invoice,
            RenderDocumentKind::forInvoiceType((string) $invoice->type),
            $payload ?? $this->designPayload($invoice),
            finalized: $invoice->party_snapshot !== null,
        );
        $recipient = $invoice->recipient();
        $buyerVatId = trim($recipient['vat_id']);

        return [
            'invoice' => $invoice,
            // Rechtsangaben aus der Org DER RECHNUNG statt aus dem Ambient-
            // Kontext: im Queue-Worker (Mail-Anhang) gibt es keinen Auth-User —
            // sonst fehlten die §14-UStG-Pflichtangaben im PDF-Footer.
            'orgLegal' => app(BrandingService::class)->legalFor($invoice->organization),
            'design' => $design,
            // Ausgestellte Rechnungen älteren Stands bleiben beim Nachdruck ohne ergänzten Hinweis.
            'taxNotes' => $design->predatesExemptionNotes() ? [] : app(TaxExemptionNotes::class)->printed($invoice),
            'issuer' => $this->issuer($invoice),
            // Ausgestellt: der beim Ausstellen eingefrorene Ansprechpartner, auch wenn sich die Person später ändert.
            'contactPerson' => is_array($invoice->party_snapshot) && array_key_exists('contact', $invoice->party_snapshot)
                ? (is_array($invoice->party_snapshot['contact']) ? $invoice->party_snapshot['contact'] : null)
                : self::contactPerson($invoice),
            // Ausgestellt: der beim Ausstellen eingefrorene Empfänger, auch nach einer Adressänderung (MVP-1624).
            'recipient' => $recipient,
            // § 14a Abs. 1 und 3 UStG; Rechnungen älteren Stands bleiben beim Nachdruck ohne die Zeile (MVP-1623).
            'buyerVatId' => $buyerVatId !== '' && ! $design->predatesBuyerVatId() ? $buyerVatId : null,
        ];
    }

    /**
     * Ansprechpartner des Belegs: die zuständige Vertriebsperson.
     *
     * @return array{name: string, phone: string, email: string}|null
     */
    public static function contactPerson(Invoice $invoice): ?array {
        $person = $invoice->salesUser;

        return $person === null ? null : [
            'name' => (string) $person->name,
            'phone' => (string) ($person->phone ?? ''),
            'email' => (string) ($person->email ?? ''),
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

    /**
     * Ausstellerangaben: der beim Ausstellen eingefrorene Stand gilt allein,
     * sonst die aktuellen Stammdaten (Entwurf).
     *
     * @return array<string, mixed>
     */
    private function issuer(Invoice $invoice): array {
        $frozen = $invoice->party_snapshot['issuer'] ?? null;

        return is_array($frozen)
            ? $frozen
            : IssuerIdentity::for($invoice->organization, is_array($invoice->party_snapshot['seller'] ?? null) ? $invoice->party_snapshot['seller'] : null);
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
