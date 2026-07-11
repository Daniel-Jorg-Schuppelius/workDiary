<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimFinancialController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Claims;

use App\Enums\Claims\ClaimFinancialKind;
use App\Http\Controllers\Controller;
use App\Models\Claims\{ClaimCase, ClaimFinancialOutcome};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Claims\ClaimFinancialService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Kaufmännische Folgen (Feature 072, MVP-252, D1): Vorschlag (manage) →
 * Vier-Augen-Freigabe (finance) → Ausführung (finance) mit Faktura-
 * Folgebeleg (Gutschrift/Storno, strukturiertes reason_kind am Beleg).
 */
class ClaimFinancialController extends Controller {
    public function __construct(private readonly ClaimFinancialService $service) {}

    public function store(Request $request, ClaimCase $claim): RedirectResponse {
        Gate::authorize('update', $claim);

        if ($request->filled('invoice_id')) {
            $request->merge(['invoice_id' => Sqid::decodeOrNumeric(\App\Models\Invoice::class, $request->input('invoice_id'))]);
        }
        $data = $request->validate([
            'kind' => ['required', Rule::enum(ClaimFinancialKind::class)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', Rule::enum(\CommonToolkit\Enums\CurrencyCode::class)],
            'invoice_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('invoices')],
            'justification' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        $this->service->propose($claim, $request->user() ?? abort(401), ClaimFinancialKind::from($data['kind']), [
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? 'EUR',
            'invoice_id' => $data['invoice_id'] ?? $claim->invoice_id,
            'justification' => $data['justification'],
        ]);

        return back()->with('status', __('Kaufmännische Folge vorgeschlagen.'));
    }

    public function approve(Request $request, ClaimFinancialOutcome $outcome): RedirectResponse {
        Gate::authorize('finance', $outcome->claimCase);

        try {
            $this->service->approve($outcome, $request->user() ?? abort(401));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['outcome' => $e->getMessage()]);
        }

        return back()->with('status', __('Folge freigegeben (Vier-Augen-Prinzip).'));
    }

    public function execute(Request $request, ClaimFinancialOutcome $outcome): RedirectResponse {
        Gate::authorize('finance', $outcome->claimCase);

        try {
            $result = $this->service->execute($outcome, $request->user() ?? abort(401));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['outcome' => $e->getMessage()]);
        }

        if ($result->result_invoice_id !== null) {
            $notice = __('Folgebeleg :number als Entwurf erzeugt.', ['number' => $result->resultInvoice?->number]);
        } elseif ($result->kind->producesInvoice()) {
            // Beleghoheit extern (Lexoffice/DATEV): dort anlegen, Nummer nachtragen.
            $notice = __('Ausgeführt — Korrekturbeleg im führenden Fakturasystem anlegen und Belegnummer nachtragen.');
        } else {
            $notice = __('Folge ausgeführt.');
        }

        return back()->with('status', $notice);
    }

    /** Externe Belegnummer (führendes System) nachtragen (MVP-252/D1). */
    public function reference(Request $request, ClaimFinancialOutcome $outcome): RedirectResponse {
        Gate::authorize('finance', $outcome->claimCase);

        $data = $request->validate(['external_reference' => ['required', 'string', 'max:100']]);

        try {
            $this->service->recordExternalReference($outcome, $data['external_reference']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['outcome' => $e->getMessage()]);
        }

        return back()->with('status', __('Externe Belegnummer nachgetragen.'));
    }
}
