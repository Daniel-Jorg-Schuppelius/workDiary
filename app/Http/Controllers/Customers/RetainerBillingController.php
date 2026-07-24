<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerBillingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Billing\RetainerLexofficeService;
use App\Support\Tz;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Retainer-Aktionen an der Kundenakte (Feature 098): Monatspauschale sofort an
 * Lexoffice senden bzw. Spitzabrechnung über den offenen Saldo erstellen.
 * Fehler (Lexoffice down, kein Saldo) werden als Flash zurückgegeben.
 */
class RetainerBillingController extends Controller {
    public function pushMonth(Request $request, Customer $customer, RetainerLexofficeService $service): RedirectResponse {
        Gate::authorize('update', $customer);
        $agreement = $customer->billingAgreement()->firstOrFail();

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        try {
            $service->pushMonthlyRetainer($agreement, (int) $data['year'], (int) $data['month']);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('customer-billing.retainer_push_failed', ['msg' => $e->getMessage()]));
        }

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.retainer_pushed'));
    }

    public function trueUp(Customer $customer, RetainerLexofficeService $service): RedirectResponse {
        Gate::authorize('update', $customer);
        $agreement = $customer->billingAgreement()->firstOrFail();

        try {
            $service->pushTrueUp($agreement, Carbon::now(Tz::current()));
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('customer-billing.retainer_push_failed', ['msg' => $e->getMessage()]));
        }

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.trueup_pushed'));
    }
}
