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
use App\Models\Audit\AuditLog;
use App\Models\Classification\ActivityCategory;
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainProjection, DomainResellerAccount};
use App\Models\Invoicing\Invoice;
use App\Models\Material\MaterialCostAllocation;
use App\Models\Platform\User;
use App\Models\Reselling\ResaleSubscription;
use App\Models\Time\TimeEntry;
use App\Services\Billing\Contracts\ExternalRevenue;
use App\Services\Billing\{CustomerAccountStatementService, PartyDocumentSources, RetainerChannelResolver};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Stammdaten\IdentifierIssueDetector;
use App\Services\Timeline\DiaryEntryTimelineService;
use App\Support\MorphMap;
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
        private readonly CustomerStatsService $stats,
        private readonly CustomerTrendBuilder $trends,
        private readonly DiaryEntryTimelineService $timeline,
        private readonly IdentifierIssueDetector $identifierIssues,
        private readonly CustomerAccountStatementService $accountStatements,
        private readonly FeatureFlagResolver $featureFlags,
        private readonly RetainerChannelResolver $retainerChannels,
        private readonly ExternalRevenue $externalRevenue,
        private readonly PartyDocumentSources $partyDocuments,
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

        // Zeit-Kennzahlen folgen der Sicht auf Zeiten anderer: ohne sie zählen
        // nur die eigenen Einträge (wie in den Berichten).
        $totalMinutes = (int) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->visibleTo($user)
            ->sum('minutes');

        $totalRate = (float) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->visibleTo($user)
            ->sum('rate');

        // Zeitraum-gebundene KPI-Werte (globaler Header-Zeitraum, AGENTS.md §8):
        // „Erfasste Zeit" und „Umsatz (kalk.)" zeigen primär den Zeitraum,
        // die Gesamtwerte nur noch als kleinen Zusatz.
        $rangeFrom = $globalRange['from']->startOfDay();
        $rangeTo = $globalRange['to']->endOfDay();
        $rangeMinutes = (int) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->visibleTo($user)
            ->whereBetween('date', DateRange::days($rangeFrom, $rangeTo))
            ->sum('minutes');
        $rangeRate = (float) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->visibleTo($user)
            ->whereBetween('date', DateRange::days($rangeFrom, $rangeTo))
            ->sum('rate');

        // Lokale Rechnungen desselben Kunden im Header-Zeitraum; die Belege der
        // Buchhaltungsprogramme ergänzen sie zur Rechnungssicht (MVP-1038).
        $localInvoices = Gate::forUser($user)->allows('viewAny', Invoice::class)
            ? Invoice::query()
            ->where('customer_id', $customer->getKey())
            ->whereBetween('issued_on', [$rangeFrom, $rangeTo])
            ->orderByDesc('issued_on')
            ->limit(500)
            ->get()
            : collect();

        // Tatsächlich fakturierter Umsatz im Zeitraum — dieselbe Abgrenzung wie
        // der Umsatztrend (Rechnungsarten ohne Stornos, ohne archivierte Belege).
        $invoicedRange = array_sum($this->externalRevenue->monthlyRevenue((int) $customer->getKey(), $rangeFrom, $rangeTo));
        foreach ($localInvoices as $inv) {
            if (in_array($inv->type, CustomerTrendBuilder::INVOICE_TYPES, true) && ! in_array($inv->status->value, CustomerTrendBuilder::VOID_STATUSES, true)) {
                $invoicedRange += $inv->total?->toFloat() ?? 0.0;
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

        // Kundenakte-Reiter „Abos" (Feature 152, MVP-758): Abos des Kunden und
        // seiner Fremdkunden (Endkunden) mit der Zahl fälliger Perioden (offen,
        // Beginn erreicht, fremder Halter) — nur mit Modul und Recht.
        $customerSubscriptions = collect();
        $customerLicenses = collect();
        $resaleModuleActive = $this->featureFlags->isEnabled('module.reselling');
        if ($resaleModuleActive && Gate::forUser($user)->allows(Permission::ResellingView->value)) {
            $today = \App\Models\Reselling\ResalePeriod::today();
            $customerSubscriptions = ResaleSubscription::query()
                ->with(['foreignCustomer:id,name'])
                ->withCount(['periods as open_periods_count' => static fn($q) => $q->due($today)])
                ->forCustomer($customer)
                ->planning()
                ->orderBy('label')
                ->limit(200)
                ->get();
            // Lizenzbestand (MVP-1024): verkaufte Einzellizenzen des Kunden und seiner Fremdkunden — ohne Schlüssel.
            $customerLicenses = \App\Models\Reselling\ResaleLicenseAssignment::query()
                ->whereNull('ended_at')
                ->where('customer_id', $customer->id)
                ->with(['unit.batch.product', 'foreignCustomer:id,name'])
                ->orderByDesc('sold_on')
                ->limit(200)
                ->get();
        }

        // Kunden-Sonderkonditionen & Abrechnungskonto (Feature 098): Panel nur
        // mit update-Recht; im saldenführenden Modus (Konto/Retainer) offene
        // Monate frisch durchrechnen. Retainer zeigt zusätzlich die Pauschalbelege
        // des Buchhaltungsprogramms je Monat (MVP-1027: über den Kanal).
        $canUpdate = Gate::forUser($user)->allows('update', $customer);
        $billingAgreement = null;
        $billingStatements = collect();
        $billingPayments = collect();
        $billingStrayEntries = [];
        $billingVouchers = [];
        $retainerSystem = null;
        if ($canUpdate) {
            $billingAgreement = $customer->billingAgreement()->with('rates.activityCategory')->first();
            if ($billingAgreement !== null && $billingAgreement->keepsLedger()) {
                $warnings = $this->accountStatements->recalculateOpen($billingAgreement);
                $billingStrayEntries = $warnings['stray_entries'];
                $billingStatements = $billingAgreement->statements()
                    ->with(['retainerInvoice'])
                    ->orderByDesc('year')->orderByDesc('month')
                    ->limit(13)
                    ->get();
                if ($billingAgreement->isRetainerMode()) {
                    $billingVouchers = $this->retainerChannels->linksFor($customer)->linkedVouchers($billingStatements);
                    $retainerSystem = $this->retainerChannels->labelFor($customer);
                }
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
        $peppolLookup = $peppolParticipant === null ? null : \App\Models\Peppol\PeppolParticipantLookup::query()
            ->where('organization_id', $customer->organization_id)
            ->where('participant', $peppolParticipant->canonical())
            ->first();

        return [
            'identifierIssues' => $this->identifierIssues->forContact($customer),
            'customer' => $customer,
            'customerDomains' => $customerDomains,
            'customerSubscriptions' => $customerSubscriptions,
            'customerLicenses' => $customerLicenses,
            'resaleModuleActive' => $resaleModuleActive,
            'portalUsers' => $portalUsers,
            'portalLastLogins' => $portalLastLogins,
            'billingAgreement' => $billingAgreement,
            'billingStatements' => $billingStatements,
            'billingPayments' => $billingPayments,
            'billingStrayEntries' => $billingStrayEntries,
            'billingVouchers' => $billingVouchers,
            'retainerSystem' => $retainerSystem,
            'billingActivityCategories' => $canUpdate
                ? ActivityCategory::query()->active()->orderBy('label')->get()
                : collect(),
            'timelineItems' => $timeline['items'],
            'timelineHasMore' => $timeline['hasMore'],
            'timelineType' => $timelineType,
            'timelineLimit' => $timelineLimit,
            'projects' => $projects,
            'defaultProject' => $defaultProject,
            'statsTotal' => $this->stats->forCustomer($customer, $user),
            'statsRange' => $this->stats->forCustomer($customer, $user, $rangeFrom, $rangeTo),
            'seesAllTimes' => $user->canViewAllTimeEntries(),
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
            'localInvoices' => $localInvoices,
            'externalDocuments' => $this->partyDocuments->forParty($customer, $rangeFrom, $rangeTo),
            'voucherRange' => $globalRange,
            'peppolLookup' => $peppolLookup,
            // Kundenvereinbarungen (Feature 157): AVV/NDA-Verträge dieses Kunden
            // mit ihrer neuesten Fassung — ohne Modul/Recht bleibt das Panel weg.
            'agreements' => $this->featureFlags->isEnabled('module.contracts') && $user->can('viewAny', \App\Models\Contract\Contract::class)
                ? \App\Models\Contract\Contract::query()
                    ->where('customer_id', $customer->id)
                    ->whereIn('kind', array_map(static fn (\App\Enums\Contract\ContractKind $k): string => $k->value, \App\Enums\Contract\ContractKind::signingKinds()))
                    ->with('latestSigningRevision')
                    ->orderByDesc('id')
                    ->get()
                : null,
            'attachments' => $customer->attachments()->get(),
            'tags' => $customer->tags()->get(),
            'auditLogs' => AuditLog::query()
                ->where('auditable_type', MorphMap::stableKey($customer::class))
                ->where('auditable_id', $customer->getKey())
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ];
    }
}
