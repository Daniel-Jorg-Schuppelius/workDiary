<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Domain;

use App\Enums\Domain\{DomainRenewalMode, DomainSyncStatus};
use App\Http\Controllers\Controller;
use App\Models\{Customer, ForeignCustomer};
use App\Models\Domain\DomainProjection;
use App\Services\Domain\{DomainCustomerMappingService, DomainInvoiceService, DomainSyncService};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Globale Domainverwaltung (Feature 083, MVP-387/394): Portfolio mit Suche/
 * Filtern und Kennzahlen sowie Domain-Detail mit Übersicht, Kontakten,
 * Nameservern/DNS, Accounting, Timeline und Providerherkunft. Aktionen sind
 * strikt rechte-/status-/capability-abhängig.
 */
class DomainController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', DomainProjection::class);

        $query = DomainProjection::query()->with(['customer:id,name', 'resellerAccount:id,external_user', 'connection:id,name']);

        if (($search = trim((string) $request->query('q', ''))) !== '') {
            $query->whereLikeEscaped('external_domain', $search);
        }
        if (($customer = $request->query('customer')) !== null && $customer !== '') {
            $model = (new Customer())->resolveRouteBinding($customer);
            $query->where('customer_id', $model?->getKey() ?? 0);
        }
        if (($status = $request->query('sync')) !== null && $status !== '' && DomainSyncStatus::tryFrom($status) !== null) {
            $query->where('sync_status', $status);
        }
        if (($mode = $request->query('renewal_mode')) !== null && $mode !== '' && DomainRenewalMode::tryFrom($mode) !== null) {
            $query->where('renewal_mode', $mode);
        }
        if (($tld = trim((string) $request->query('tld', ''))) !== '') {
            $query->whereLikeEscaped('external_domain', '.' . ltrim($tld, '.'));
        }
        if (($days = (int) $request->query('expiry_within', 0)) > 0) {
            $query->whereNotNull('expiration_at')->where('expiration_at', '<=', Carbon::now()->addDays($days));
        }

        $domains = $query->orderBy('external_domain')->paginate(25)->withQueryString();

        return view('domain.index', [
            'domains' => $domains,
            'metrics' => $this->metrics(),
            'filters' => $request->only(['q', 'customer', 'sync', 'renewal_mode', 'tld', 'expiry_within']),
        ]);
    }

    public function show(DomainProjection $domain, DomainInvoiceService $invoices, DomainCustomerMappingService $mapping): View {
        Gate::authorize('view', $domain);

        $domain->load(['customer', 'foreignCustomer', 'resellerAccount', 'connection', 'dnsZones.records']);
        $canAssign = Gate::allows('assignCustomer', $domain);
        /** @var \App\Models\Domain\DomainProviderConnection $connection */
        $connection = $domain->connection;
        $commands = \App\Models\Domain\DomainProviderCommand::query()
            ->where('connection_id', $domain->connection_id)
            ->where('target', $domain->external_domain)
            ->latest('id')
            ->limit(20)
            ->get();

        return view('domain.show', [
            'domain' => $domain,
            'commands' => $commands,
            'invoicesAvailable' => $invoices->isAvailable($connection),
            'invoiceBlockedReason' => $invoices->blockedReason(),
            // Zuordnungsdaten: Kundenliste, Endkunden des zugeordneten Kunden
            // und Match-Vorschläge (nur solange keine Zuordnung besteht).
            'customers' => $canAssign ? Customer::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'foreignCustomers' => $canAssign && $domain->customer_id !== null
                ? ForeignCustomer::query()->where('customer_id', $domain->customer_id)->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'customer_id'])
                : collect(),
            'suggestions' => $canAssign && $domain->customer_id === null && ! $domain->is_own_holding
                ? $mapping->suggestFor($domain)
                : [],
            'can' => [
                'assign' => $canAssign,
                'contacts' => Gate::allows('manageContacts', $domain),
                'dns' => Gate::allows('manageDns', $domain),
                'renewal' => Gate::allows('manageRenewal', $domain),
                'transfer' => Gate::allows('manageTransfer', $domain),
                'dangerous' => Gate::allows('approveDangerous', $domain),
            ],
        ]);
    }

    /** Detailabgleich einer Domain über StatusDomain. */
    public function refresh(DomainProjection $domain, DomainSyncService $sync): RedirectResponse {
        Gate::authorize('view', $domain);

        $sync->refreshDomain($domain);

        return back()->with('success', __('domain.flash.refreshed'));
    }

    /** Kunden-/Endkunden-Zuordnung oder Eigenbestand setzen (kein Provider-Move). */
    public function assignCustomer(Request $request, DomainProjection $domain, DomainCustomerMappingService $mapping): RedirectResponse {
        Gate::authorize('assignCustomer', $domain);

        $data = $request->validate([
            'customer' => ['nullable', 'string'],
            'foreign_customer' => ['nullable', 'string'],
            'own' => ['nullable', 'in:0,1'],
        ]);

        // Eigenbestand-Umschalter (eigenes kleines Formular in der Karte).
        if (($data['own'] ?? null) !== null) {
            $data['own'] === '1' ? $mapping->markOwnHolding($domain) : $mapping->clearOwnHolding($domain);

            return back()->with('success', __('domain.flash.mapping_saved'));
        }

        if (($data['customer'] ?? null) === null || $data['customer'] === '') {
            $mapping->clearAssignment($domain);

            return back()->with('success', __('domain.flash.mapping_cleared'));
        }

        $customer = (new Customer())->resolveRouteBinding($data['customer']);
        if (! $customer instanceof Customer) {
            abort(404);
        }

        $foreign = null;
        if (($data['foreign_customer'] ?? null) !== null && $data['foreign_customer'] !== '') {
            $foreign = (new ForeignCustomer())->resolveRouteBinding($data['foreign_customer']);
            if (! $foreign instanceof ForeignCustomer || $foreign->customer_id !== $customer->id) {
                return back()->with('error', __('domain.flash.foreign_customer_mismatch'));
            }
        }
        $mapping->assign($domain, $customer, $request->user(), $foreign);

        return back()->with('success', __('domain.flash.mapping_saved'));
    }

    /** @return array<string, int> */
    private function metrics(): array {
        $base = fn () => DomainProjection::query();

        return [
            'expiring_90' => $base()->whereNotNull('expiration_at')->whereBetween('expiration_at', [Carbon::now(), Carbon::now()->addDays(90)])->count(),
            'risky' => $base()->whereIn('renewal_mode', [DomainRenewalMode::Autoexpire->value, DomainRenewalMode::Autodelete->value])->count(),
            // Wie DomainReportService::unmapped(): Eigenbestand und Domains,
            // deren Reseller-Account bereits einem Kunden zugeordnet ist,
            // zählen nicht als „ohne Kundenzuordnung".
            'unmapped' => $base()->whereNull('customer_id')->where('is_own_holding', false)
                ->where(function ($q): void {
                    $q->whereNull('reseller_account_id')
                        ->orWhereDoesntHave('resellerAccount', fn ($r) => $r->whereNotNull('customer_id'));
                })->count(),
            'sync_issues' => $base()->whereIn('sync_status', [DomainSyncStatus::Conflict->value, DomainSyncStatus::Unknown->value])->count(),
        ];
    }
}
