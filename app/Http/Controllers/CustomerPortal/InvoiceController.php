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
use App\Services\Billing\Contracts\ExternalRevenue;
use App\Services\Billing\Dto\{ExternalInvoice, ExternalInvoiceFile, ExternalInvoiceQuery};
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\InvoicePdfRenderer;
use App\Services\Invoicing\OnlinePayment\InvoicePaymentLinkService;
use App\Support\MorphMap;
use ERechnungToolkit\Enums\ERechnungProfile;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Route};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvoiceController extends Controller {
    /**
     * Lokale Rechnungen und die des Buchhaltungsprogramms (MVP-1118) in einer
     * Liste nach Datum; das Programm liefert dieselbe Belegregel wie die
     * Auswertungen (ohne Entwürfe und Stornos).
     */
    public function index(Request $request): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        // Entwürfe sind interne Arbeitsstände (MVP-1019) — der Kunde sieht nur Ausgestelltes.
        $local = Invoice::query()
            ->where('customer_id', $user->customer_id)
            ->where('status', '!=', InvoiceStatus::Draft)
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->get();

        $rows = $local->map(static fn (Invoice $invoice): array => [
            'invoice' => $invoice,
            'number' => (string) $invoice->number,
            'date' => $invoice->issued_on,
            'statusLabel' => $invoice->status->label(),
            'open' => in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true),
            'total' => $invoice->total?->toFloat() ?? 0.0,
            'currency' => $invoice->currency->value,
            'pdf' => route('customer.invoices.pdf', $invoice),
        ])->all();
        foreach ($this->externalInvoices($user) as $invoice) {
            $rows[] = [
                'invoice' => null,
                'number' => $invoice->number,
                'date' => $invoice->issuedOn,
                'statusLabel' => $invoice->status->label(),
                'open' => $invoice->open > 0,
                'total' => $invoice->gross,
                'currency' => $invoice->currency,
                'pdf' => route('customer.invoices.external', ['source' => $invoice->source, 'key' => $invoice->key]),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => ($b['date']?->getTimestamp() ?? 0) <=> ($a['date']?->getTimestamp() ?? 0));

        // Jahr als Tabs, Status und Monat als Filter (Phase 154, MVP-1243) — Ziel der Drilldowns aus der Auswertung.
        // Belege ohne Datum zählen zum laufenden Jahr, damit sie nicht aus jeder Sicht fallen.
        $rowYear = static fn (array $row): int => (int) ($row['date']->year ?? now()->year);
        $years = array_values(array_unique(array_map($rowYear, $rows)));
        rsort($years);
        $year = \App\Services\CustomerPortal\PortalInsights::pickYear((int) $request->query('year', 0), $years);
        $status = in_array($request->query('status'), ['open', 'paid'], true) ? (string) $request->query('status') : null;
        $month = (int) $request->query('month', 0);
        $month = $month >= 1 && $month <= 12 ? $month : null;
        $rows = array_values(array_filter($rows, static fn (array $row): bool => $rowYear($row) === $year
            && ($month === null || ($row['date'] !== null && (int) $row['date']->month === $month))
            && ($status === null || ($status === 'open') === $row['open'])));

        $page = LengthAwarePaginator::resolveCurrentPage();
        $invoices = new LengthAwarePaginator(array_slice($rows, ($page - 1) * 25, 25), count($rows), 25, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        // Online-Zahlung (MVP-1067): im Portal unabhängig vom Schalter für PDF und Mail.
        $links = app(InvoicePaymentLinkService::class);
        $payLinks = collect($invoices->items())
            ->map(static fn (array $row): ?Invoice => $row['invoice'])
            ->filter()
            ->mapWithKeys(static fn (Invoice $invoice): array => [$invoice->id => $links->urlFor($invoice, onDocuments: false)])
            ->filter();

        return view('customer.invoices.index', [
            'invoices' => $invoices,
            'payLinks' => $payLinks,
            'year' => $year,
            'years' => $years === [] ? [$year] : $years,
            'status' => $status,
            'month' => $month,
        ]);
    }

    /** Belegdatei einer Rechnung des Buchhaltungsprogramms — nur, wenn sie in der Liste des Kunden steht. */
    public function external(string $source, string $key): SymfonyResponse {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        $listed = collect($this->externalInvoices($user))
            ->contains(static fn (ExternalInvoice $invoice): bool => $invoice->source === $source && $invoice->key === $key);
        abort_unless($listed, 404);

        $file = app(ExternalRevenue::class)->file($source, (int) $user->organization_id, $key);

        return $file !== null
            ? $this->download($file)
            : redirect()->toList('customer.invoices.index')->with('error', __('customer_portal.invoices.document_unavailable'));
    }

    /** @return list<ExternalInvoice> */
    private function externalInvoices(User $user): array {
        return app(ExternalRevenue::class)->invoices(new ExternalInvoiceQuery(
            customerId: (int) $user->customer_id,
            organizationId: (int) $user->organization_id,
        ));
    }

    private function download(ExternalInvoiceFile $file): SymfonyResponse {
        return response($file->body, 200, [
            'Content-Type' => $file->contentType,
            'Content-Disposition' => 'attachment; filename="' . $file->filename . '"',
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
        // Führt ein Fakturaprogramm die Rechnung, liegt das gültige Dokument dort (MVP-1118).
        if ($this->externallyLed(collect([$invoice]))->isNotEmpty()) {
            $file = app(ExternalRevenue::class)->fileForInvoice($invoice);

            return $file !== null
                ? $this->download($file)
                : redirect()->toList('customer.invoices.index')->with('error', __('customer_portal.invoices.document_unavailable'));
        }

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
