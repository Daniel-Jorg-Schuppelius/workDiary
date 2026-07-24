<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountPaymentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Customers;

use App\Enums\Billing\AccountPaymentSource;
use App\Http\Controllers\Controller;
use App\Models\Billing\CustomerAccountPayment;
use App\Models\{Customer, User};
use App\Services\Billing\CustomerAccountStatementService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\ValidationException;

/**
 * Zahlungen auf dem Kundenkonto (Feature 098): manuelles Buchen + Stornieren.
 * Bank-gematchte Zahlungen entstehen/verschwinden ausschließlich über die
 * Zahlungszuordnung (ReconciliationService), nicht hier.
 */
class AccountPaymentController extends Controller {
    /** Modal-Fragment „Zahlung buchen" (data-entry-modal-trigger). */
    public function create(Customer $customer): \Illuminate\View\View {
        Gate::authorize('update', $customer);
        $customer->billingAgreement()->firstOrFail();

        return view('customers.billing._payment_form_dialog', [
            'customer' => $customer,
        ]);
    }

    public function store(Request $request, Customer $customer, CustomerAccountStatementService $service): RedirectResponse {
        Gate::authorize('update', $customer);

        $data = $request->validate([
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'not_in:0', 'min:-9999999', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $agreement = $customer->billingAgreement()->firstOrFail();

        /** @var User $actor */
        $actor = Auth::user();
        $service->bookPayment($agreement, [
            'paid_on' => $data['paid_on'],
            'amount' => (float) $data['amount'],
            'note' => $data['note'] ?? null,
        ], $actor);

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.flash_payment_booked'));
    }

    public function destroy(Customer $customer, CustomerAccountPayment $payment, CustomerAccountStatementService $service): RedirectResponse {
        Gate::authorize('update', $customer);
        abort_unless(
            $payment->agreement()->where('customer_id', $customer->id)->exists(),
            404
        );

        if (in_array($payment->source, [AccountPaymentSource::Bank, AccountPaymentSource::Lexoffice], true)) {
            // Bank-/Lexoffice-gematchte Zahlungen werden über die jeweilige
            // Zuordnung bzw. den Belegstatus verwaltet, nicht manuell storniert.
            throw ValidationException::withMessages([
                'payment' => __('customer-billing.error_bank_payment_unmatch'),
            ]);
        }

        $agreement = $payment->agreement()->firstOrFail();

        $lockedMonth = $agreement->statements()
            ->where('year', $payment->paid_on->year)
            ->where('month', $payment->paid_on->month)
            ->where('locked', true)
            ->exists();
        if ($lockedMonth) {
            throw ValidationException::withMessages([
                'payment' => __('customer-billing.error_target_month_locked'),
            ]);
        }

        $payment->delete();
        $service->recalculateOpen($agreement);

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.flash_payment_voided'));
    }
}
