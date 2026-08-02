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
use Illuminate\View\View;

/**
 * Monatsabschluss-Aktionen des Kundenkontos (Feature 098): Abschließen (Lock +
 * Snapshot + exported), Wiedereröffnen, Neuberechnen. Ketteninvarianten
 * validiert der Service hart.
 */
class BillingStatementController extends Controller {
    /**
     * Monatsdetail für die Verwaltung: dieselben Zeilen wie Portal und PDF
     * (monthData ist die einzige Datenquelle) — gesperrt aus dem Snapshot,
     * offen live gerechnet.
     */
    public function show(Customer $customer, CustomerBillingStatement $statement, CustomerAccountStatementService $service): View {
        // Wie das Panel an der Kundenakte: Abrechnungsdaten (Sätze, Beträge)
        // sind nicht für jeden Org-Mitleser.
        Gate::authorize('update', $customer);
        $this->assertBelongsToCustomer($customer, $statement);

        $agreement = $statement->agreement()->firstOrFail();
        $data = $service->monthData($agreement, $statement->year, $statement->month);

        return view('customers.billing.statement', [
            'customer' => $customer,
            'agreement' => $agreement,
            'statement' => $data['statement'],
            'rows' => $data['rows'],
            'payments' => $data['payments'],
            'byCategory' => $data['by_category'],
            'locked' => $data['locked'],
        ]);
    }

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
        // reapplyRates statt recalculateOpen: der Knopf soll auch Zeiten
        // nachbewerten, die vor Anlage der Kondition erfasst wurden — reines
        // Nachrechnen der Statements ließe sie bei 0,00 € stehen.
        $service->reapplyRates($agreement);

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
