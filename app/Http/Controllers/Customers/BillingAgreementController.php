<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingAgreementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBillingAgreementRequest;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\Customer;
use App\Services\Billing\CustomerAccountStatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{DB, Gate};

/**
 * Sonderkonditions-Profil an der Kundenakte (Feature 098): Anlegen/Ändern
 * inkl. Satzzeilen; Satzänderungen werden sofort auf offene Monate angewendet
 * (reapplyRates — manuelle Overrides bleiben unberührt).
 */
class BillingAgreementController extends Controller {
    /** Modal-Fragment für Anlegen/Bearbeiten (data-entry-modal-trigger). */
    public function edit(Customer $customer): \Illuminate\View\View {
        Gate::authorize('update', $customer);

        return view('customers.billing._agreement_form_dialog', [
            'customer' => $customer,
            'agreement' => $customer->billingAgreement()->with('rates')->first(),
            'activityCategories' => \App\Models\ActivityCategory::query()->active()->orderBy('label')->get(),
        ]);
    }

    public function save(
        SaveBillingAgreementRequest $request,
        Customer $customer,
        CustomerAccountStatementService $statements
    ): RedirectResponse {
        Gate::authorize('update', $customer);

        DB::transaction(function () use ($request, $customer): void {
            /** @var CustomerBillingAgreement $agreement */
            $agreement = CustomerBillingAgreement::query()->updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'organization_id' => $customer->organization_id,
                    'mode' => $request->validated('mode'),
                    'currency' => $request->validated('currency'),
                    'expected_monthly_amount' => $request->validated('expected_monthly_amount'),
                    'workdays_per_week' => (int) $request->validated('workdays_per_week'),
                    'opening_balance' => (float) ($request->validated('opening_balance') ?? 0),
                    'opening_balance_date' => $request->validated('opening_balance_date'),
                    'active' => $request->boolean('active'),
                    'notes' => $request->validated('notes'),
                ],
            );

            // Satzzeilen als Ganzes ersetzen (MVP ohne Historien-UI; valid_from
            // bleibt der DB/dem Import vorbehalten).
            $agreement->rates()->delete();
            foreach ($request->rateRows() as $row) {
                CustomerBillingRate::create([
                    'organization_id' => $agreement->organization_id,
                    'customer_billing_agreement_id' => $agreement->id,
                    'activity_category_id' => $row['activity_category_id'],
                    'day_type' => $row['day_type'],
                    'hourly_rate' => $row['hourly_rate'],
                ]);
            }
        });

        $agreement = $customer->billingAgreement()->firstOrFail();
        if ($agreement->keepsLedger()) {
            $statements->reapplyRates($agreement);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('customer-billing.flash_agreement_saved'));
    }
}
