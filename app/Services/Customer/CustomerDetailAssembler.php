<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDetailAssembler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Customer;

use App\Enums\User\Permission;
use App\Models\{ActivityCategory, AuditLog, Customer, ExternalReference, Invoice, LexofficeVoucher, MaterialCostAllocation, TimeEntry, User};
use App\Models\Domain\{DomainProjection, DomainResellerAccount};
use App\Plugins\Contracts\PluginCapability;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\PluginManager;
use App\Services\Billing\CustomerAccountStatementService;
use App\Services\CustomerStatsService;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Stammdaten\IdentifierIssueDetector;
use App\Services\Timeline\DiaryEntryTimelineService;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Gate};

/**
 * Aggregiert alle Daten der Kundenakte (KPIs, Belege/Rechnungen, Monats-Trends,
 * Timeline, Domains, Abrechnungskonto, Portalzugänge) inkl. Sichtbarkeits-
 * prüfung je Betrachter. Aus CustomerController::show() extrahiert
 * (Vollscan 2026-08-23, B21) — die Autorisierung des Aufrufs selbst bleibt im
 * Controller; der globale Header-Zeitraum kommt als Parameter herein
 * (Services haben kein Request-Concern).
 */
class CustomerDetailAssembler {
    public function __construct(
        private readonly PluginManager $plugins,
        private readonly CustomerStatsService $stats,
        private readonly CustomerTrendBuilder $trends,
        private readonly DiaryEntryTimelineService $timeline,
        private readonly IdentifierIssueDetector $identifierIssues,
        private readonly CustomerAccountStatementService $accountStatements,
        private readonly FeatureFlagResolver $featureFlags,
    ) {}

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, preset: string, effectivePreset: string, label: string, unit: string, isoWeekLabel: ?string}  $globalRange  global gewählter Header-Zeitraum (ResolvesGlobalDateRange)
     * @return array<string, mixed> View-Daten für customers.show
     */
    public function assemble(Customer $customer, User $user, array $globalRange, string $timelineType, int $timelineLimit): array {
        $defaultProject = $customer->defaultProjectOrCreate();

        $projects = $customer->projects()
            ->with('foreignCustomer:id,name')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $projectIds = $projects->pluck('id')->map(static fn($id): int => (int) $id)->all();

        $totalMinutes = (int) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->sum('minutes');

        $totalRate = (float) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->sum('rate');

        // Zeitraum-gebundene KPI-Werte (globaler Header-Zeitraum, AGENTS.md §8):
        // „Erfasste Zeit" und „Umsatz (kalk.)" zeigen primär den Zeitraum,
        // die Gesamtwerte nur noch als kleinen Zusatz.
        $rangeFrom = $globalRange['from']->startOfDay();
        $rangeTo = $globalRange['to']->endOfDay();
        $rangeMinutes = (int) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('date', DateRange::days($rangeFrom, $rangeTo))
            ->sum('minutes');
        $rangeRate = (float) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('date', DateRange::days($rangeFrom, $rangeTo))
            ->sum('rate');

        $lexoffice = $this->plugins->withCapability(PluginCapability::TimeExport)->get(LexofficePlugin::ID);
        $lexofficeContactRef = $lexoffice
            ? ExternalReference::query()
            ->forPlugin($customer->organization_id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->forReferenceable($customer)
            ->first()
            : null;
        $lexofficeVouchers = $lexoffice
            ? ExternalReference::query()
            ->forPlugin($customer->organization_id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_VOUCHER)
            ->forReferenceable($customer)
            ->orderByDesc('synced_at')
            ->limit(10)
            ->get()
            : collect();

        // Lexoffice-Belege auf den globalen Header-Zeitraum eingrenzen (wie die übrigen zeitraumbezogenen Ansichten).
        $lexofficeVoucherRange = $globalRange;

        // Lokale Rechnungen desselben Kunden im Header-Zeitraum (ergänzen die Lexoffice-Belege zur Rechnungssicht).
        $localInvoices = Gate::forUser($user)->allows('viewAny', Invoice::class)
            ? Invoice::query()
            ->where('customer_id', $customer->getKey())
            ->whereBetween('issued_on', [$rangeFrom, $rangeTo])
            ->orderByDesc('issued_on')
            ->limit(500)
            ->get()
            : collect();

        // Lexoffice-Belege desselben Kunden im Header-Zeitraum (Belegsicht +
        // fakturierter Umsatz der KPI).
        $lexofficeVoucherCache = $lexoffice
            ? LexofficeVoucher::query()
            ->where('customer_id', $customer->getKey())
            ->where('archived', false)
            ->whereBetween('voucher_date', [$rangeFrom, $rangeTo])
            ->orderByDesc('voucher_date')
            ->limit(500)
            // Alle Kopfspalten, aber ohne den longtext-Payload je Beleg
            // (Vollscan 2026-08-23, A9) — die Belegliste zeigt nur Kopfdaten.
            ->get(['id', 'organization_id', 'external_id', 'contact_external_id', 'customer_id', 'supplier_id', 'voucher_type', 'voucher_status', 'voucher_number', 'voucher_date', 'due_date', 'paid_date', 'total_amount', 'open_amount', 'net_amount', 'currency', 'archived', 'synced_at', 'created_at', 'updated_at'])
            : collect();

        // Tatsächlich fakturierter Umsatz im Zeitraum (Lexoffice-Belege + lokale
        // Rechnungen) — ergänzt den kalkulatorischen Umsatz aus erfassten Zeiten.
        // Gleiche Logik wie die Rechnungssumme in partials/_documents.
        $invoicedRange = 0.0;
        foreach ($localInvoices as $inv) {
            if (in_array($inv->type, CustomerTrendBuilder::INVOICE_TYPES, true) && ! in_array($inv->status, CustomerTrendBuilder::VOID_STATUSES, true)) {
                $invoicedRange += $inv->total?->toFloat() ?? 0.0;
            }
        }
        foreach ($lexofficeVoucherCache as $voucher) {
            if (in_array($voucher->voucher_type, CustomerTrendBuilder::INVOICE_TYPES, true) && ! in_array($voucher->voucher_status, CustomerTrendBuilder::VOID_STATUSES, true)) {
                $invoicedRange += $voucher->total_amount?->toFloat() ?? 0.0;
            }
        }

        // Einem Kunden zugeordnete Materialkosten im Zeitraum + Gewinn.
        $materialAllocations = $customer->materialCostAllocations()
            ->with(['project:id,name', 'source'])
            ->orderByDesc('allocated_on')
            ->limit(100)
            ->get();
        $materialRange = (float) $customer->materialCostAllocations()
            ->whereBetween('allocated_on', DateRange::days($rangeFrom, $rangeTo))
            ->get()
            ->sum(static fn(MaterialCostAllocation $a): float => $a->allocated_amount?->toFloat() ?? 0.0);
        $profitRange = $invoicedRange - $materialRange;

        // Kompakte 12-Monats-Trends (Zeiteinsatz + fakturierter Umsatz vs.
        // Materialkosten) für die Diagramme, verankert am Ende des Header-Zeitraums.
        $monthlyTrends = $this->trends->build($customer, $projectIds, $rangeTo, $user);

        // Vollwertige Kunden-Timeline (MVP-340): serverseitiger Typ-Filter + Nachlade-Fenster (Muster wie DiaryController).
        $timeline = $this->timeline->forCustomer(
            $customer,
            $user,
            $timelineType !== '' ? [$timelineType] : null,
            $timelineLimit,
        );

        // Kundenakte-Reiter „Domains" (Feature 083, MVP-394; Vollaudit 2026-07,
        // M34): direkt zugeordnete Domains + Domains der zugeordneten
        // Reseller-Accounts (Subuser); nur mit domain.viewAny sichtbar.
        $customerDomains = collect();
        if (Gate::forUser($user)->allows(Permission::DomainViewAny->value)) {
            $resellerAccountIds = DomainResellerAccount::query()
                ->where('customer_id', $customer->id)
                ->pluck('id');
            $customerDomains = DomainProjection::query()
                ->with('foreignCustomer:id,name')
                ->where(function ($q) use ($customer, $resellerAccountIds): void {
                    $q->where('customer_id', $customer->id);
                    if ($resellerAccountIds->isNotEmpty()) {
                        $q->orWhereIn('reseller_account_id', $resellerAccountIds->all());
                    }
                })
                ->orderBy('external_domain')
                ->limit(200)
                ->get();
        }

        // Kunden-Sonderkonditionen & Abrechnungskonto (Feature 098): Panel nur
        // mit update-Recht; im saldenführenden Modus (Konto/Retainer) offene
        // Monate frisch durchrechnen. Retainer zeigt zusätzlich die Lexoffice-
        // Pauschalbelege je Monat.
        $canUpdate = Gate::forUser($user)->allows('update', $customer);
        $billingAgreement = null;
        $billingStatements = collect();
        $billingPayments = collect();
        $billingStrayEntries = [];
        if ($canUpdate) {
            $billingAgreement = $customer->billingAgreement()->with('rates.activityCategory')->first();
            if ($billingAgreement !== null && $billingAgreement->keepsLedger()) {
                $warnings = $this->accountStatements->recalculateOpen($billingAgreement);
                $billingStrayEntries = $warnings['stray_entries'];
                $billingStatements = $billingAgreement->statements()
                    ->with(['retainerInvoice', 'lexofficeVoucher'])
                    ->orderByDesc('year')->orderByDesc('month')
                    ->limit(13)
                    ->get();
                $billingPayments = $billingAgreement->payments()
                    ->orderByDesc('paid_on')
                    ->limit(12)
                    ->get();
            }
        }

        // Portalzugänge (MVP-510): Panel nur mit Verwaltungs-Permission; die
        // letzte Anmeldung kommt aus der geteilten sessions-Tabelle (database-
        // Driver), max(last_activity) je Portalkonto.
        $portalUsers = collect();
        $portalLastLogins = [];
        if ($user->isAdmin() || Gate::forUser($user)->allows(Permission::CustomerPortalAccessManage->value)) {
            $portalUsers = User::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $customer->organization_id)
                ->where('customer_id', $customer->id)
                ->orderBy('name')
                ->get();
            if ($portalUsers->isNotEmpty() && config('session.driver') === 'database') {
                $portalLastLogins = DB::table('sessions')
                    ->whereIn('user_id', $portalUsers->pluck('id')->all())
                    ->groupBy('user_id')
                    ->selectRaw('user_id, MAX(last_activity) as last_activity')
                    ->pluck('last_activity', 'user_id')
                    ->map(fn($ts) => Carbon::createFromTimestamp((int) $ts))
                    ->all();
            }
        }

        // Peppol-Registrierungsstand (Feature 066, MVP-734): der zuletzt
        // gespeicherte SMP-Befund, nie eine Live-Auflösung im Seitenaufbau.
        $peppolParticipant = \App\Services\Peppol\PeppolParticipantService::forCustomer($customer);
        $peppolLookup = $peppolParticipant === null ? null : \App\Models\PeppolParticipantLookup::query()
            ->where('organization_id', $customer->organization_id)
            ->where('participant', $peppolParticipant->canonical())
            ->first();

        return [
            'identifierIssues' => $this->identifierIssues->forContact($customer),
            'customer' => $customer,
            'customerDomains' => $customerDomains,
            'portalUsers' => $portalUsers,
            'portalLastLogins' => $portalLastLogins,
            'billingAgreement' => $billingAgreement,
            'billingStatements' => $billingStatements,
            'billingPayments' => $billingPayments,
            'billingStrayEntries' => $billingStrayEntries,
            'billingActivityCategories' => $canUpdate
                ? ActivityCategory::query()->active()->orderBy('label')->get()
                : collect(),
            'timelineItems' => $timeline['items'],
            'timelineHasMore' => $timeline['hasMore'],
            'timelineType' => $timelineType,
            'timelineLimit' => $timelineLimit,
            'projects' => $projects,
            'defaultProject' => $defaultProject,
            'statsTotal' => $this->stats->forCustomer($customer),
            'statsRange' => $this->stats->forCustomer($customer, $rangeFrom, $rangeTo),
            'statsRangeLabel' => $globalRange['label'],
            'totalMinutes' => $totalMinutes,
            'totalRate' => $totalRate,
            'rangeMinutes' => $rangeMinutes,
            'rangeRate' => $rangeRate,
            'invoicedRange' => $invoicedRange,
            'materialRange' => $materialRange,
            'profitRange' => $profitRange,
            'materialAllocations' => $materialAllocations,
            'inventoryModuleActive' => $this->featureFlags->isEnabled('module.lager'),
            'chartHours' => $monthlyTrends['hours'],
            'chartRevenue' => $monthlyTrends['revenue'],
            'lexofficePlugin' => $lexoffice,
            'lexofficeContactRef' => $lexofficeContactRef,
            'lexofficeVouchers' => $lexofficeVouchers,
            'lexofficeVoucherRange' => $lexofficeVoucherRange,
            'localInvoices' => $localInvoices,
            'lexofficeVoucherCache' => $lexofficeVoucherCache,
            'peppolLookup' => $peppolLookup,
            'attachments' => $customer->attachments()->get(),
            'tags' => $customer->tags()->get(),
            'auditLogs' => AuditLog::query()
                ->where('auditable_type', $customer->getMorphClass())
                ->where('auditable_id', $customer->getKey())
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ];
    }
}
