<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingStatementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Billing\CustomerBillingStatement;
use App\Models\{Customer, User};
use App\Services\Billing\CustomerAccountStatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Monatsabschluss-Aktionen des Kundenkontos (Feature 098): Abschließen (Lock +
 * Snapshot + exported), Wiedereröffnen, Neuberechnen. Ketteninvarianten
 * validiert der Service hart.
 */
class BillingStatementController extends Controller {
    public function close(Customer $customer, CustomerBillingStatement $statement, CustomerAccountStatementService $service): RedirectResponse {
        Gate::authorize('update', $customer);
        $this->assertBelongsToCustomer($customer, $statement);

        /** @var User $actor */
        $actor = Auth::user();
        $service->close($statement, $actor);

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.flash_closed', ['period' => $statement->periodLabel()]));
    }

    public function reopen(Customer $customer, CustomerBillingStatement $statement, CustomerAccountStatementService $service): RedirectResponse {
        Gate::authorize('update', $customer);
        $this->assertBelongsToCustomer($customer, $statement);

        /** @var User $actor */
        $actor = Auth::user();
        $service->reopen($statement, $actor);

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.flash_reopened', ['period' => $statement->periodLabel()]));
    }

    public function recalculate(Customer $customer, CustomerAccountStatementService $service): RedirectResponse {
        Gate::authorize('update', $customer);

        $agreement = $customer->billingAgreement()->firstOrFail();
        $service->recalculateOpen($agreement);

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.flash_recalculated'));
    }

    private function assertBelongsToCustomer(Customer $customer, CustomerBillingStatement $statement): void {
        abort_unless(
            $statement->agreement()->where('customer_id', $customer->id)->exists(),
            404
        );
    }
}
