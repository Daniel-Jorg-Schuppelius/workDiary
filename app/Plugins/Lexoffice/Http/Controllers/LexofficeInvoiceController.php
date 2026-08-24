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
use App\Models\{ExternalReference, Invoice};
use App\Plugins\Lexoffice\{LexofficeInvoiceService, LexofficePlugin};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Plugin-eigener Controller, der Aktionen rund um die Lexoffice-Invoice
 * (Push, PDF-Proxy, Status-Sync) kapselt — damit der Core-InvoiceController
 * Lexoffice nicht mehr direkt kennen muss.
 */
class LexofficeInvoiceController extends Controller {
    public function __construct(
        private readonly LexofficeInvoiceService $lexofficeInvoice,
    ) {}

    public function publish(Invoice $invoice): RedirectResponse {
        Gate::authorize('issue', $invoice);

        if (! $this->lexofficeInvoice->isConfigured()) {
            return back()->with('error', __('Lexoffice-Plugin ist nicht aktiviert oder API-Key fehlt.'));
        }

        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            return back()->with('error', __('Nur Entwürfe können an Lexoffice übertragen werden.'));
        }

        // Dieselben Voraussetzungen wie die lokale Ausstellung (Freigabepflicht,
        // E-Rechnungs-Validierung) — der Push stellt die Rechnung (B1).
        try {
            app(\App\Services\Invoicing\InvoiceIssueService::class)->assertIssuable($invoice);
        } catch (\App\Services\Invoicing\InvoiceIssueException $e) {
            return back()->with('error', $e->getMessage());
        }

        $contactRef = ExternalReference::query()
            ->forPlugin($invoice->customer->organization_id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->forReferenceable($invoice->customer)
            ->first();

        try {
            $this->lexofficeInvoice->publish($invoice, $contactRef?->external_id, finalize: true);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Lexoffice-Übertragung fehlgeschlagen: :msg', ['msg' => $e->getMessage()]));
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('Rechnung an Lexoffice übertragen und finalisiert.'));
    }

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

        $pdf = $this->lexofficeInvoice->downloadPdf($ref->external_id);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rechnung-' . $invoice->number . '.pdf"',
        ]);
    }
}
