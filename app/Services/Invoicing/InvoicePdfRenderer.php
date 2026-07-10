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

use App\Models\{Invoice, InvoiceTemplate};
use App\Services\BrandingService;
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

/**
 * Rendert die Druckansicht einer Rechnung (`invoices.pdf`) zu PDF-Bytes über
 * die pdf-toolkit `PDFWriterRegistry`. Geteilt von Controller-Download,
 * Mail-Anhang und der WebDAV-Spiegelung (Rang 19), damit alle exakt dasselbe
 * Dokument erzeugen (gleiche Vorlage + Rechtsangaben).
 */
class InvoicePdfRenderer {
    /** PDF-Bytes der Rechnung (A4). */
    public function output(Invoice $invoice): string {
        $html = view('invoices.pdf', $this->viewData($invoice))->render();

        return PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($html))
            ?? throw new RuntimeException('PDF-Erzeugung fehlgeschlagen (invoices.pdf).');
    }

    /**
     * View-Daten der Druckansicht: gewählte Vorlage (Kunde > Org-Default) +
     * Rechtsangaben der Organisation.
     *
     * @return array{invoice: Invoice, template: InvoiceTemplate|null, orgLegal: mixed}
     */
    public function viewData(Invoice $invoice): array {
        $template = $invoice->customer->invoice_template_id
            ? InvoiceTemplate::query()->find($invoice->customer->invoice_template_id)
            : InvoiceTemplate::query()
                ->where('organization_id', $invoice->organization_id)
                ->where('is_default', true)
                ->first();

        return [
            'invoice' => $invoice,
            'template' => $template,
            // Rechtsangaben aus der Org DER RECHNUNG statt aus dem Ambient-
            // Kontext: im Queue-Worker (Mail-Anhang) gibt es keinen Auth-User —
            // sonst fehlten die §14-UStG-Pflichtangaben im PDF-Footer.
            'orgLegal' => app(BrandingService::class)->legalFor($invoice->organization),
        ];
    }
}
