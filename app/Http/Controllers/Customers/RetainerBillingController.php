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
use App\Models\Customer\Customer;
use App\Services\Billing\RetainerChannelResolver;
use App\Support\{ErrorText, Tz};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Retainer-Aktionen an der Kundenakte (Feature 098): Monatspauschale sofort an
 * das Buchhaltungsprogramm senden, Spitzabrechnung über den offenen Saldo
 * erstellen und einen dort bereits geführten Beleg von Hand an einen Monat
 * hängen. Welches Programm, entscheidet der {@see RetainerChannelResolver}
 * (MVP-1027). Fehler (Programm nicht erreichbar, kein Saldo) kommen als Flash.
 */
class RetainerBillingController extends Controller {
    public function __construct(private readonly RetainerChannelResolver $channels) {}

    public function pushMonth(Request $request, Customer $customer): RedirectResponse {
        Gate::authorize('manageBilling', $customer);
        $agreement = $customer->billingAgreement()->firstOrFail();

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $publisher = $this->channels->publisherFor($customer);
        try {
            $publisher->pushMonthlyRetainer($agreement, (int) $data['year'], (int) $data['month']);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('customer-billing.retainer_push_failed', ['system' => $publisher->label(), 'msg' => ErrorText::for($e)]));
        }

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.retainer_pushed', ['system' => $publisher->label()]));
    }

    public function trueUp(Customer $customer): RedirectResponse {
        Gate::authorize('manageBilling', $customer);
        $agreement = $customer->billingAgreement()->firstOrFail();

        $publisher = $this->channels->publisherFor($customer);
        try {
            $publisher->pushTrueUp($agreement, Carbon::now(Tz::current()));
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('customer-billing.retainer_push_failed', ['system' => $publisher->label(), 'msg' => ErrorText::for($e)]));
        }

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.trueup_pushed', ['system' => $publisher->label()]));
    }

    /** Modal-Fragment: bereits im Buchhaltungsprogramm geführten Beleg an den Monat hängen. */
    public function editVoucher(Customer $customer, CustomerBillingStatement $statement): View {
        Gate::authorize('manageBilling', $customer);
        $this->assertBelongsToCustomer($customer, $statement);

        return view('customers.billing._voucher_link_dialog', [
            'customer' => $customer,
            'statement' => $statement,
            'system' => $this->channels->labelFor($customer),
            'linked' => $this->channels->linksFor($customer)->linkedVouchers([$statement])[$statement->id] ?? null,
            'vouchers' => $this->channels->linksFor($customer)->linkableVouchers($customer, $statement),
        ]);
    }

    public function linkVoucher(Request $request, Customer $customer, CustomerBillingStatement $statement): RedirectResponse {
        Gate::authorize('manageBilling', $customer);
        $this->assertBelongsToCustomer($customer, $statement);

        if ($statement->retainer_invoice_id !== null) {
            return back()->with('error', __('customer-billing.retainer_invoice_already_pushed'));
        }

        $key = (string) $request->validate(['voucher' => ['required', 'string', 'max:64']])['voucher'];
        $links = $this->channels->linksFor($customer);
        $voucher = $links->link($statement, $key);
        // Zahlung sofort nachziehen, damit der Saldo nicht bis zum Cron wartet.
        $links->reconcile($customer->organization()->firstOrFail());

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.voucher_linked', ['number' => (string) ($voucher->number ?? $voucher->externalId)]));
    }

    public function unlinkVoucher(Customer $customer, CustomerBillingStatement $statement): RedirectResponse {
        Gate::authorize('manageBilling', $customer);
        $this->assertBelongsToCustomer($customer, $statement);

        $this->channels->linksFor($customer)->unlink($statement);

        return redirect()->route('customers.show', $customer)
            ->with('status', __('customer-billing.voucher_unlinked'));
    }

    private function assertBelongsToCustomer(Customer $customer, CustomerBillingStatement $statement): void {
        abort_unless(
            $statement->agreement()->where('customer_id', $customer->id)->exists(),
            404
        );
    }
}
