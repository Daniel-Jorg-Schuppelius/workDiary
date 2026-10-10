<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Invoicing\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Billing\BillingModeResolver;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\InvoicePdfRenderer;
use App\Services\Invoicing\OnlinePayment\InvoicePaymentLinkService;
use App\Support\MorphMap;
use ERechnungToolkit\Enums\ERechnungProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Route};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvoiceController extends Controller {
    public function index(): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        // Entwürfe sind interne Arbeitsstände (MVP-1019) — der Kunde sieht nur Ausgestelltes.
        $invoices = Invoice::query()
            ->where('customer_id', $user->customer_id)
            ->where('status', '!=', InvoiceStatus::Draft)
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate(25);

        // Online-Zahlung (MVP-1067): im Portal unabhängig vom Schalter für PDF und Mail.
        $links = app(InvoicePaymentLinkService::class);
        $payLinks = $invoices->getCollection()
            ->mapWithKeys(static fn (Invoice $invoice): array => [$invoice->id => $links->urlFor($invoice, onDocuments: false)])
            ->filter();

        return view('customer.invoices.index', [
            'invoices' => $invoices,
            'payLinks' => $payLinks,
            'externallyLed' => $this->externallyLed($invoices->getCollection()),
        ]);
    }

    /**
     * Rechnungsdokument (MVP-1097): dasselbe Dokument wie der interne Download
     * und der Mail-Anhang — finalisierte Rechnungen rendern aus ihrem
     * eingefrorenen Render-Snapshot. Wer per ZUGFeRD beliefert wird, erhält
     * das ZUGFeRD-PDF wie im Versand.
     */
    public function pdf(Invoice $invoice, InvoicePdfRenderer $renderer, XRechnungGenerator $generator): SymfonyResponse {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        // Ohne Organisationskontext greift kein Scope: Zugehörigkeit ausdrücklich prüfen.
        abort_unless(
            (int) $invoice->customer_id === (int) $user->customer_id
            && (int) $invoice->organization_id === (int) $user->organization_id
            && $invoice->status !== InvoiceStatus::Draft,
            404,
        );
        // Führt ein Fakturaprogramm die Rechnung, liegt das gültige Dokument dort.
        abort_if($this->externallyLed(collect([$invoice]))->isNotEmpty(), 404);

        $invoice->load(['items', 'customer', 'project']);
        $number = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $invoice->number);

        $zugferd = $this->zugferd($invoice, $renderer, $generator);
        if ($zugferd !== null) {
            return response($zugferd, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="ZUGFeRD_' . $number . '.pdf"',
            ]);
        }

        return response($renderer->output($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rechnung-' . $number . '.pdf"',
        ]);
    }

    /**
     * ZUGFeRD-PDF unter denselben Bedingungen wie der interne Download; null,
     * wenn die Rechnung nicht so zugestellt wird oder es nicht erzeugbar ist —
     * dann gilt die sichtbar gleiche Rechnungsdarstellung.
     */
    private function zugferd(Invoice $invoice, InvoicePdfRenderer $renderer, XRechnungGenerator $generator): ?string {
        if (! $invoice->delivery_format->needsZugferd()
            || $invoice->isProforma()
            || app(BillingModeResolver::class)->effectiveFor($invoice->customer)->isExternal()
            || ! $generator->zugferdAvailable()
            || $generator->preflight($invoice, ERechnungProfile::EN16931)['errors'] !== []) {
            return null;
        }

        return $generator->generateZugferdPdf($invoice, $renderer->composedHtml($invoice));
    }

    /**
     * Rechnungen, deren PDF intern ein Plugin liefert (Naht wie
     * Invoicing\InvoiceController::pdf).
     *
     * @param Collection<int, Invoice> $invoices
     * @return Collection<int, int> Rechnungs-IDs
     */
    private function externallyLed(Collection $invoices): Collection {
        if ($invoices->isEmpty()) {
            return collect();
        }

        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('external_type', 'invoice')
            ->where('referenceable_type', MorphMap::alias(Invoice::class))
            ->whereIn('referenceable_id', $invoices->map(static fn (Invoice $invoice): int => (int) $invoice->getKey())->all())
            ->get(['referenceable_id', 'plugin_id'])
            ->filter(static fn (ExternalReference $ref): bool => Route::has('invoices.' . $ref->plugin_id . '.pdf'))
            ->map(static fn (ExternalReference $ref): int => (int) $ref->referenceable_id)
            ->values();
    }
}
