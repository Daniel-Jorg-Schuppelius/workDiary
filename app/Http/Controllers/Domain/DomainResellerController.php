<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResellerController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Domain;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Domain\{DomainAccountingEntry, DomainResellerAccount};
use App\Services\Domain\{DomainCustomerMappingService, DomainInvoiceService};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Reselleransicht (Feature 083, MVP-392): Hierarchiebaum mit direkten und
 * verschachtelten Subusern, je Reseller Kunde/Aktivstatus/Währung/Saldo/
 * Domainanzahl, Reiter „Buchungen" und ein Reiter „Rechnungen" nur als
 * Blocked-State (Capability nicht belegt).
 */
class DomainResellerController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', DomainResellerAccount::class);

        $accounts = DomainResellerAccount::query()
            ->with(['customer:id,name', 'connection:id,name'])
            ->withCount('domains')
            ->orderBy('depth')
            ->orderBy('external_user')
            ->get();

        return view('domain.reseller.index', ['accounts' => $accounts]);
    }

    public function show(DomainResellerAccount $reseller, DomainInvoiceService $invoices): View {
        Gate::authorize('view', $reseller);

        $reseller->load(['customer', 'connection', 'domains.customer', 'domains.foreignCustomer']);
        $entries = DomainAccountingEntry::query()
            ->where('reseller_account_id', $reseller->id)
            ->orderByDesc('entry_date')
            ->limit(100)
            ->get();
        $canAssign = Gate::allows('assignCustomer', $reseller);

        return view('domain.reseller.show', [
            'reseller' => $reseller,
            'entries' => $entries,
            'canAssign' => $canAssign,
            'customers' => $canAssign ? Customer::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'canViewAccounting' => Gate::allows('viewAccounting', $reseller),
            'invoicesAvailable' => $invoices->isAvailable($reseller->providerConnection()),
            'invoiceBlockedReason' => $invoices->blockedReason(),
        ]);
    }

    public function assignCustomer(Request $request, DomainResellerAccount $reseller, DomainCustomerMappingService $mapping): RedirectResponse {
        Gate::authorize('assignCustomer', $reseller);

        $data = $request->validate(['customer' => ['nullable', 'string']]);
        if (($data['customer'] ?? null) === null || $data['customer'] === '') {
            $reseller->forceFill(['customer_id' => null])->save();

            return back()->with('success', __('domain.flash.mapping_cleared'));
        }

        $customer = (new Customer())->resolveRouteBinding($data['customer']);
        if (! $customer instanceof Customer) {
            abort(404);
        }
        $mapping->assignReseller($reseller, $customer);

        return back()->with('success', __('domain.flash.mapping_saved'));
    }

    /**
     * Schreibt die Kundenzuordnung des Subusers FEST auf alle seine aktuellen
     * Domains (Bulk). Setzt einen zugeordneten Kunden am Reseller voraus.
     */
    public function assignDomains(Request $request, DomainResellerAccount $reseller, DomainCustomerMappingService $mapping): RedirectResponse {
        Gate::authorize('assignCustomer', $reseller);

        if ($reseller->customer_id === null) {
            return back()->with('error', __('domain.flash.reseller_customer_required'));
        }

        $count = $mapping->assignResellerDomains($reseller, $request->user());

        return back()->with('success', __('domain.flash.reseller_domains_assigned', ['count' => $count]));
    }
}
