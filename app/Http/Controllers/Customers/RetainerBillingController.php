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
use App\Models\Billing\CustomerBillingStatement;
use App\Models\{Customer, LexofficeVoucher};
use App\Services\Billing\{RetainerLexofficeService, RetainerVoucherReconciler};
use App\Support\Tz;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Retainer-Aktionen an der Kundenakte (Feature 098): Monatspauschale sofort an
 * Lexoffice senden, Spitzabrechnung über den offenen Saldo erstellen und einen
 * bereits in Lexoffice geführten Beleg von Hand an einen Monat hängen.
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

    /** Modal-Fragment: bereits in Lexoffice geführten Beleg an den Monat hängen. */
    public function editVoucher(Customer $customer, CustomerBillingStatement $statement, RetainerVoucherReconciler $reconciler): View {
        Gate::authorize('update', $customer);
        $this->assertBelongsToCustomer($customer, $statement);

        $organization = $customer->organization()->firstOrFail();

        return view('customers.billing._voucher_link_dialog', [
            'customer' => $customer,
            'statement' => $statement,
            'vouchers' => $reconciler->linkableVouchers($organization, $customer->id, $statement->id),
        ]);
    }

    public function linkVoucher(
        Request $request,
        Customer $customer,
        CustomerBillingStatement $statement,
        RetainerVoucherReconciler $reconciler
    ): RedirectResponse {
        Gate::authorize('update', $customer);
        $this->assertBelongsToCustomer($customer, $statement);

        if ($statement->retainer_invoice_id !== null) {
            return back()->with('error', __('customer-billing.retainer_invoice_already_pushed'));
        }

        $voucher = LexofficeVoucher::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->findOrFail($this->voucherIdFrom($request));

        $reconciler->link($statement, $voucher);
        $this->reconcileNow($customer, $reconciler);

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.voucher_linked', ['number' => (string) $voucher->voucher_number]));
    }

    public function unlinkVoucher(Customer $customer, CustomerBillingStatement $statement, RetainerVoucherReconciler $reconciler): RedirectResponse {
        Gate::authorize('update', $customer);
        $this->assertBelongsToCustomer($customer, $statement);

        $reconciler->unlink($statement);

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.voucher_unlinked'));
    }

    /** Beleg-Sqid → ID; das Formular führt wie überall keine rohen IDs. */
    private function voucherIdFrom(Request $request): int {
        $sqid = (string) $request->validate([
            'voucher' => ['required', 'string'],
        ])['voucher'];

        $voucher = (new LexofficeVoucher)->resolveRouteBinding($sqid);
        if (! $voucher instanceof LexofficeVoucher) {
            throw ValidationException::withMessages(['voucher' => __('customer-billing.voucher_not_found')]);
        }

        return $voucher->id;
    }

    /** Zahlung sofort nachziehen, damit der Saldo nicht bis zum Cron wartet. */
    private function reconcileNow(Customer $customer, RetainerVoucherReconciler $reconciler): void {
        $organization = $customer->organization()->first();
        if ($organization !== null) {
            $reconciler->reconcile($organization);
        }
    }

    private function assertBelongsToCustomer(Customer $customer, CustomerBillingStatement $statement): void {
        abort_unless(
            $statement->agreement()->where('customer_id', $customer->id)->exists(),
            404
        );
    }
}
