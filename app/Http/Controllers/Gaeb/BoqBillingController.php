<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqBillingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Gaeb;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Gaeb\BillOfQuantity;
use App\Services\Billing\BillingModeLockedException;
use App\Services\Gaeb\BoqBillingService;
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Rechnungspakete und Zahlungshistorie je LV (MVP-932). */
class BoqBillingController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly BoqBillingService $billing) {}

    public function show(BillOfQuantity $billOfQuantity): View {
        Gate::authorize(P::InvoiceViewAny->value);
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        return view('bill-of-quantities.billing', [
            'bill' => $billOfQuantity,
            'proposal' => $this->billing->proposal($billOfQuantity),
            'history' => $this->billing->history($billOfQuantity),
            'canInvoice' => Gate::allows(P::InvoiceCreate->value),
        ]);
    }

    public function store(Request $request, BillOfQuantity $billOfQuantity): RedirectResponse {
        Gate::authorize(P::InvoiceCreate->value);
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);
        try {
            $invoice = $this->billing->createDownPayment($billOfQuantity, (float) $data['amount']);
        } catch (BillingModeLockedException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('invoices.show', $invoice)->with('success', __('gaeb.billing.flash.created', ['number' => $invoice->number]));
    }
}
