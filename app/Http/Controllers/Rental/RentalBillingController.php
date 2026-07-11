<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalBillingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Rental;

use App\Enums\Rental\RentalChargeKind;
use App\Http\Controllers\Controller;
use App\Models\Rental\{RentalCharge, RentalDeposit};
use App\Services\Rental\RentalBillingService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Kaufmännische Folge des Verleihs (MVP-266): Positionen, Freigabe,
 * Faktura-Übergabe (Beleghoheit!) und Kaution als eigener Finanzvorgang.
 * Autorisierung läuft gegen die finance-Ability der Verleihakte.
 */
class RentalBillingController extends Controller {
    public function __construct(private readonly RentalBillingService $billing) {}

    public function storeCharge(Request $request, \App\Models\Rental\RentalCase $rental): RedirectResponse {
        Gate::authorize('finance', $rental);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(RentalChargeKind::class)],
            'label' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', 'string', 'max:20'],
            'unit_price' => ['required', 'numeric'],
            'reason_text' => ['nullable', 'string', 'max:4000'],
        ]);

        try {
            $this->billing->addCharge($rental, $request->user() ?? abort(401), $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['reason_text' => $e->getMessage()]);
        }

        return back()->with('status', __('Position erfasst.'));
    }

    /** Vorschläge aus dem Konditionen-Snapshot übernehmen (D10). */
    public function applySuggestions(Request $request, \App\Models\Rental\RentalCase $rental): RedirectResponse {
        Gate::authorize('finance', $rental);

        $suggestions = $this->billing->suggestCharges($rental);
        if ($suggestions === []) {
            return back()->withErrors(['charges' => __('Keine Konditionen im Snapshot — Preisliste an der Akte wählen.')]);
        }

        $actor = $request->user() ?? abort(401);
        foreach ($suggestions as $suggestion) {
            $this->billing->addCharge($rental, $actor, $suggestion);
        }

        return back()->with('status', __(':count Positionen aus den Konditionen übernommen.', ['count' => count($suggestions)]));
    }

    public function releaseCharge(Request $request, RentalCharge $charge): RedirectResponse {
        Gate::authorize('finance', $charge->rentalCase()->firstOrFail());

        try {
            $this->billing->releaseCharge($charge, $request->user() ?? abort(401));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['charge' => $e->getMessage()]);
        }

        return back()->with('status', __('Position freigegeben.'));
    }

    public function cancelCharge(Request $request, RentalCharge $charge): RedirectResponse {
        Gate::authorize('finance', $charge->rentalCase()->firstOrFail());

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->billing->cancelCharge($charge, $request->user() ?? abort(401), $data['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['charge' => $e->getMessage()]);
        }

        return back()->with('status', __('Position storniert.'));
    }

    /** Faktura-Übergabe: lokaler Beleg ODER externe Beleghoheit (MVP-266). */
    public function invoice(Request $request, \App\Models\Rental\RentalCase $rental): RedirectResponse {
        Gate::authorize('finance', $rental);

        try {
            $invoice = $this->billing->invoiceReleasedCharges($rental, $request->user() ?? abort(401));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['charges' => $e->getMessage()]);
        }

        if ($invoice === null) {
            return back()->with('status', __('Positionen an das führende Fakturasystem übergeben — externe Belegnummer nachtragen.'));
        }

        return back()->with('status', __('Rechnungsentwurf :number erzeugt.', ['number' => $invoice->number]));
    }

    public function externalReference(Request $request, RentalCharge $charge): RedirectResponse {
        Gate::authorize('finance', $charge->rentalCase()->firstOrFail());

        $data = $request->validate(['external_reference' => ['required', 'string', 'max:255']]);

        try {
            $this->billing->recordExternalReference($charge, $data['external_reference']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['external_reference' => $e->getMessage()]);
        }

        return back()->with('status', __('Externe Belegnummer hinterlegt.'));
    }

    public function requestDeposit(Request $request, \App\Models\Rental\RentalCase $rental): RedirectResponse {
        Gate::authorize('finance', $rental);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->billing->requestDeposit($rental, $request->user() ?? abort(401), (float) $data['amount'], $data['note'] ?? null);

        return back()->with('status', __('Kaution angefordert.'));
    }

    public function receiveDeposit(Request $request, RentalDeposit $deposit): RedirectResponse {
        Gate::authorize('finance', $deposit->rentalCase()->firstOrFail());

        try {
            $this->billing->markDepositReceived($deposit, $request->user() ?? abort(401));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['deposit' => $e->getMessage()]);
        }

        return back()->with('status', __('Kaution als erhalten markiert.'));
    }

    public function settleDeposit(Request $request, RentalDeposit $deposit): RedirectResponse {
        Gate::authorize('finance', $deposit->rentalCase()->firstOrFail());

        $data = $request->validate([
            'retained_amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->billing->settleDeposit(
                $deposit,
                $request->user() ?? abort(401),
                (float) ($data['retained_amount'] ?? 0),
                $data['reason'] ?? null,
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['retained_amount' => $e->getMessage()]);
        }

        return back()->with('status', __('Kaution abgerechnet.'));
    }
}
