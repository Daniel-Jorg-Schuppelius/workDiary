<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeHandoverController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Http\Controllers;

use App\Enums\Lexoffice\LexofficeHandoverStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Article\ArticleExportController;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Invoicing\Invoice;
use App\Plugins\Lexoffice\Handover\LexofficeInvoiceHandoverService;
use App\Plugins\Lexoffice\Tariff\LexwareTariffService;
use App\Support\{ErrorText, Sqid};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Übergabeliste an Lexware (Feature 158, MVP-833): ausgestellte lokale
 * Belege mit getrenntem Rechnungs-, Versand- und Übergabestatus; Export als
 * Paket mit Zuordnungsliste, manuelle Bestätigung mit Benutzer und Zeitpunkt.
 * Datierte Listen folgen dem globalen Header-Zeitraum.
 */
class LexofficeHandoverController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly LexofficeInvoiceHandoverService $handovers,
        private readonly LexwareTariffService $tariffs,
    ) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::InvoiceViewAny->value);
        $organization = $this->authUser()->organization ?? abort(403);
        [$from, $to] = $this->globalDateRangeBounds();
        $statusFilter = LexofficeHandoverStatus::tryFrom($request->string('status')->toString());

        $invoices = $this->handovers->candidates($organization, $from, $to)->get();
        $states = $this->handovers->statesFor($invoices->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all());
        if ($statusFilter !== null) {
            $invoices = $invoices->filter(function (Invoice $invoice) use ($states, $statusFilter): bool {
                $state = $states[$invoice->id] ?? null;

                return $statusFilter === LexofficeHandoverStatus::Pending ? $state === null : ($state !== null && $state->status === $statusFilter);
            })->values();
        }

        return view('lexoffice::handover.index', [
            'invoices' => $invoices,
            'states' => $states,
            'statusFilter' => $statusFilter,
            'profile' => $this->tariffs->profile(),
            'canExport' => Gate::allows(Permission::InvoiceExport->value),
            'rangeLabel' => $this->globalDateRange()['label'],
        ]);
    }

    /** Paket aus den ausgewählten Belegen (PDF je Beleg + Zuordnungsliste). */
    public function export(Request $request): Response|RedirectResponse {
        Gate::authorize(Permission::InvoiceExport->value);
        $organization = $this->authUser()->organization ?? abort(403);
        $data = $request->validate([
            'invoices' => ['required', 'array', 'min:1', 'max:200'],
            'invoices.*' => ['string'],
        ]);
        $ids = array_map(static fn (string $sqid): int => Sqid::decodeOrAbort(Invoice::class, $sqid, 422), array_values((array) $data['invoices']));
        $invoices = Invoice::query()->whereKey($ids)->with('customer')->get();
        if ($invoices->count() !== count($ids)) {
            abort(404);
        }

        return $this->zip($organization, $invoices);
    }

    /** Einzelbeleg-Paket — der Knopf an der Rechnung. */
    public function exportOne(Invoice $invoice): Response|RedirectResponse {
        Gate::authorize(Permission::InvoiceExport->value);
        $organization = $this->authUser()->organization ?? abort(403);

        return $this->zip($organization, Invoice::query()->whereKey($invoice->id)->with('customer')->get());
    }

    public function confirm(Request $request, Invoice $invoice): RedirectResponse {
        Gate::authorize(Permission::InvoiceExport->value);
        $data = $request->validate(['confirmation_note' => ['nullable', 'string', 'max:500']]);

        try {
            $this->handovers->confirm($invoice, $this->authUser(), $data['confirmation_note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['confirmation_note' => ErrorText::for($e)]);
        }

        return back()->with('status', __('lexware.flash.confirmed', ['number' => (string) $invoice->number]));
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Invoice> $invoices */
    private function zip(\App\Models\Platform\Organization $organization, \Illuminate\Database\Eloquent\Collection $invoices): Response|RedirectResponse {
        try {
            $files = $this->handovers->export($organization, $invoices, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->withErrors(['invoices' => ErrorText::for($e)]);
        }

        return ArticleExportController::buildZipResponse($files, 'lexware-uebergabe-' . now()->format('Y-m-d') . '.zip');
    }
}
