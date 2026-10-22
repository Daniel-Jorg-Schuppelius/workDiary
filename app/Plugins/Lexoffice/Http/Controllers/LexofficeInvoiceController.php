<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\LexofficeInvoiceService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * PDF-Proxy einer an Lexoffice übertragenen Rechnung. Übertragen wird beim
 * Stellen über den {@see \App\Plugins\Lexoffice\Services\LexofficeDocumentIssuer}
 * (Phase 145, MVP-1166) — der Core-InvoiceController kennt Lexoffice nicht.
 */
class LexofficeInvoiceController extends Controller {
    /**
     * Liefert das Lexoffice-PDF einer verknüpften Rechnung. Aufrufer ist
     * der Core-InvoiceController via Hook, der hierhin redirected wenn
     * eine ExternalReference existiert.
     */
    public function pdf(Invoice $invoice): SymfonyResponse {
        Gate::authorize('view', $invoice);

        $ref = ExternalReference::query()
            ->forPlugin($invoice->organization_id, LexofficePlugin::ID, LexofficeInvoiceService::EXT_TYPE_INVOICE)
            ->forReferenceable($invoice)
            ->firstOrFail();

        $pdf = (new LexofficeInvoiceService($invoice->organization_id))->downloadPdf($ref->external_id, (string) ($ref->payload['_resource'] ?? LexofficeInvoiceService::RESOURCE_INVOICES));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rechnung-' . $invoice->number . '.pdf"',
        ]);
    }
}
