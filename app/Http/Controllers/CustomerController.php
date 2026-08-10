<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Import\ImportEntity;
use App\Http\Controllers\Concerns\{ArchivesModels, ParsesIndexQuery, ResolvesGlobalDateRange};
use App\Http\Requests\SaveCustomerRequest;
use App\Models\{AuditLog, Customer, ExternalReference, Invoice, LexofficeVoucher, MaterialCostAllocation, Organization, Tag, TimeEntry, User};
use App\Plugins\Contracts\PluginCapability;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\PluginManager;
use App\Services\CustomerStatsService;
use App\Services\Import\DirectCsvImportService;
use App\Services\Stammdaten\{ContactMasterDataPusher, IdentifierIssueDetector};
use App\Support\{CsvExport, Setting};
// HINWEIS: Lexoffice-Push liegt im Plugin (LexofficeCustomerController); Imports oben nur noch für die Show-View.
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller {
    use ArchivesModels;
    use ParsesIndexQuery;
    use ResolvesGlobalDateRange;

    private const ALLOWED_SORTS = ['name', 'number', 'company', 'created_at'];

    public function index(Request $request, PluginManager $plugins): View {
        Gate::authorize('viewAny', Customer::class);

        ['status' => $status, 'search' => $search, 'sort' => $sort, 'dir' => $dir]
            = $this->parseIndexQuery($request, self::ALLOWED_SORTS, 'name');

        // Lexoffice „alle pushen" nur bei org-aktivem Plugin anbieten (gleicher Check wie die Aktion).
        $lexofficeEnabled = $plugins->withCapability(PluginCapability::TimeExport)->get(LexofficePlugin::ID) !== null;

        $customers = Customer::query()
            ->search($search)
            ->when($status === 'active', fn($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn($q) => $q->whereNotNull('archived_at'))
            ->when($status === 'billable_pending', function ($q): void {
                $q->whereNull('archived_at')->withUnexportedBillable();
            })
            ->withCount('projects')
            ->orderBy($sort, $dir)
            ->paginate((int) Setting::get('pagination.customers', 25))
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'lexofficeEnabled' => $lexofficeEnabled,
        ]);
    }

    public function show(Customer $customer, PluginManager $plugins, CustomerStatsService $stats): View {
        Gate::authorize('view', $customer);

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
        [$rangeFrom, $rangeTo] = $this->globalDateRangeBounds();
        $rangeMinutes = (int) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('date', [$rangeFrom->toDateString(), $rangeTo->toDateString()])
            ->sum('minutes');
        $rangeRate = (float) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('date', [$rangeFrom->toDateString(), $rangeTo->toDateString()])
            ->sum('rate');

        $lexoffice = $plugins->withCapability(PluginCapability::TimeExport)->get(LexofficePlugin::ID);
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
        $lexofficeVoucherRange = $this->globalDateRange();

        // Lokale Rechnungen desselben Kunden im Header-Zeitraum (ergänzen die Lexoffice-Belege zur Rechnungssicht).
        $localInvoices = Gate::allows('viewAny', Invoice::class)
            ? Invoice::query()
            ->where('customer_id', $customer->getKey())
            ->whereBetween('issued_on', [
                $lexofficeVoucherRange['from']->startOfDay(),
                $lexofficeVoucherRange['to']->endOfDay(),
            ])
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
            ->whereBetween('voucher_date', [
                $lexofficeVoucherRange['from']->startOfDay(),
                $lexofficeVoucherRange['to']->endOfDay(),
            ])
            ->orderByDesc('voucher_date')
            ->limit(500)
            ->get()
            : collect();

        // Tatsächlich fakturierter Umsatz im Zeitraum (Lexoffice-Belege + lokale
        // Rechnungen) — ergänzt den kalkulatorischen Umsatz aus erfassten Zeiten.
        // Gleiche Logik wie die Rechnungssumme in partials/_documents.
        $invoiceTypes = ['invoice', 'salesinvoice', 'purchaseinvoice'];
        $voidStatuses = ['voided', 'cancelled'];
        $invoicedRange = 0.0;
        foreach ($localInvoices as $inv) {
            if (in_array($inv->type, $invoiceTypes, true) && ! in_array($inv->status, $voidStatuses, true)) {
                $invoicedRange += $inv->total?->toFloat() ?? 0.0;
            }
        }
        foreach ($lexofficeVoucherCache as $voucher) {
            if (in_array($voucher->voucher_type, $invoiceTypes, true) && ! in_array($voucher->voucher_status, $voidStatuses, true)) {
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
            ->whereBetween('allocated_on', [$rangeFrom->toDateString(), $rangeTo->toDateString()])
            ->get()
            ->sum(static fn(MaterialCostAllocation $a): float => $a->allocated_amount?->toFloat() ?? 0.0);
        $profitRange = $invoicedRange - $materialRange;

        // Kompakte 12-Monats-Trends (Zeiteinsatz + fakturierter Umsatz vs.
        // Materialkosten) für die Diagramme, verankert am Ende des Header-Zeitraums.
        $monthlyTrends = $this->customerMonthlyTrends($customer, $projectIds, $rangeTo);

        // Vollwertige Kunden-Timeline (MVP-340): serverseitiger Typ-Filter + Nachlade-Fenster (Muster wie DiaryController).
        /** @var User $viewer */
        $viewer = Auth::user();
        $timelineType = (string) request()->query('timeline_type', '');
        $timelineLimit = max(1, min(500, (int) request()->query('timeline_limit', 15)));
        $timeline = app(\App\Services\Timeline\DiaryEntryTimelineService::class)->forCustomer(
            $customer,
            $viewer,
            $timelineType !== '' ? [$timelineType] : null,
            $timelineLimit,
        );

        // Kundenakte-Reiter „Domains" (Feature 083, MVP-394; Vollaudit 2026-07,
        // M34): direkt zugeordnete Domains + Domains der zugeordneten
        // Reseller-Accounts (Subuser); nur mit domain.viewAny sichtbar.
        $customerDomains = collect();
        if (Gate::allows(\App\Enums\User\Permission::DomainViewAny->value)) {
            $resellerAccountIds = \App\Models\Domain\DomainResellerAccount::query()
                ->where('customer_id', $customer->id)
                ->pluck('id');
            $customerDomains = \App\Models\Domain\DomainProjection::query()
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
        $billingAgreement = null;
        $billingStatements = collect();
        $billingPayments = collect();
        $billingStrayEntries = [];
        if (Gate::allows('update', $customer)) {
            $billingAgreement = $customer->billingAgreement()->with('rates.activityCategory')->first();
            if ($billingAgreement !== null && $billingAgreement->keepsLedger()) {
                $warnings = app(\App\Services\Billing\CustomerAccountStatementService::class)
                    ->recalculateOpen($billingAgreement);
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
        if ($viewer->isAdmin() || Gate::allows(\App\Enums\User\Permission::CustomerPortalAccessManage->value)) {
            $portalUsers = \App\Models\User::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $customer->organization_id)
                ->where('customer_id', $customer->id)
                ->orderBy('name')
                ->get();
            if ($portalUsers->isNotEmpty() && config('session.driver') === 'database') {
                $portalLastLogins = \Illuminate\Support\Facades\DB::table('sessions')
                    ->whereIn('user_id', $portalUsers->pluck('id')->all())
                    ->groupBy('user_id')
                    ->selectRaw('user_id, MAX(last_activity) as last_activity')
                    ->pluck('last_activity', 'user_id')
                    ->map(fn($ts) => \Illuminate\Support\Carbon::createFromTimestamp((int) $ts))
                    ->all();
            }
        }

        return view('customers.show', [
            'identifierIssues' => app(IdentifierIssueDetector::class)->forContact($customer),
            'customer' => $customer,
            'customerDomains' => $customerDomains,
            'portalUsers' => $portalUsers,
            'portalLastLogins' => $portalLastLogins,
            'billingAgreement' => $billingAgreement,
            'billingStatements' => $billingStatements,
            'billingPayments' => $billingPayments,
            'billingStrayEntries' => $billingStrayEntries,
            'billingActivityCategories' => Gate::allows('update', $customer)
                ? \App\Models\ActivityCategory::query()->active()->orderBy('label')->get()
                : collect(),
            'timelineItems' => $timeline['items'],
            'timelineHasMore' => $timeline['hasMore'],
            'timelineType' => $timelineType,
            'timelineLimit' => $timelineLimit,
            'projects' => $projects,
            'defaultProject' => $defaultProject,
            'statsTotal' => $stats->forCustomer($customer),
            'statsRange' => $stats->forCustomer($customer, ...$this->globalDateRangeBounds()),
            'statsRangeLabel' => $this->globalDateRange()['label'],
            'totalMinutes' => $totalMinutes,
            'totalRate' => $totalRate,
            'rangeMinutes' => $rangeMinutes,
            'rangeRate' => $rangeRate,
            'invoicedRange' => $invoicedRange,
            'materialRange' => $materialRange,
            'profitRange' => $profitRange,
            'materialAllocations' => $materialAllocations,
            'inventoryModuleActive' => app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.lager'),
            'chartHours' => $monthlyTrends['hours'],
            'chartRevenue' => $monthlyTrends['revenue'],
            'lexofficePlugin' => $lexoffice,
            'lexofficeContactRef' => $lexofficeContactRef,
            'lexofficeVouchers' => $lexofficeVouchers,
            'lexofficeVoucherRange' => $lexofficeVoucherRange,
            'localInvoices' => $localInvoices,
            'lexofficeVoucherCache' => $lexofficeVoucherCache,
            'attachments' => $customer->attachments()->get(),
            'tags' => $customer->tags()->get(),
            'auditLogs' => AuditLog::query()
                ->where('auditable_type', $customer->getMorphClass())
                ->where('auditable_id', $customer->getKey())
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * Kompakte Monats-Trends (letzte 12 Monate bis $anchor) für die Kundenakte:
     * Zeiteinsatz (abrechenbar / nicht abrechenbar) und tatsächlich fakturierter
     * Umsatz (Lexoffice-Belege + lokale Rechnungen, gleiche Typ-/Statuslogik wie
     * die Umsatz-KPI). Buckets werden in PHP gefüllt (DB-agnostisch).
     *
     * @param  array<int>  $projectIds
     * @return array{hours: list<array<string, float|string>>, revenue: list<array<string, float|string>>}
     */
    private function customerMonthlyTrends(Customer $customer, array $projectIds, CarbonImmutable $anchor): array {
        $start = $anchor->startOfMonth()->subMonthsNoOverflow(11);
        $end = $anchor->endOfMonth();

        /** @var array<string, CarbonImmutable> $months */
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->addMonthsNoOverflow($i);
            $months[$m->format('Y-m')] = $m;
        }

        // Vorjahresvergleich: dieselben Monate ein Jahr früher als compare-Linie/-Spalte.
        $prevStart = $start->subYearsNoOverflow(1);
        /** @var array<string, string> $prevKeyOf  aktueller Y-m => Vorjahres-Y-m */
        $prevKeyOf = [];
        foreach ($months as $ym => $m) {
            $prevKeyOf[$ym] = $m->subYearsNoOverflow(1)->format('Y-m');
        }
        $prevMinutes = array_fill_keys(array_values($prevKeyOf), 0);
        $prevRevenue = array_fill_keys(array_values($prevKeyOf), 0.0);

        $minutes = array_fill_keys(array_keys($months), 0);
        $billableMinutes = array_fill_keys(array_keys($months), 0);
        if ($projectIds !== []) {
            TimeEntry::query()
                ->whereIn('project_id', $projectIds)
                ->whereBetween('date', [$prevStart->toDateString(), $end->toDateString()])
                ->get(['date', 'minutes', 'billable'])
                ->each(function (TimeEntry $e) use (&$minutes, &$billableMinutes, &$prevMinutes): void {
                    $ym = $e->date instanceof \Carbon\CarbonInterface ? $e->date->format('Y-m') : substr((string) $e->date, 0, 7);
                    if (isset($minutes[$ym])) {
                        $minutes[$ym] += (int) $e->minutes;
                        if ($e->billable) {
                            $billableMinutes[$ym] += (int) $e->minutes;
                        }
                    } elseif (isset($prevMinutes[$ym])) {
                        $prevMinutes[$ym] += (int) $e->minutes;
                    }
                });
        }

        // Fakturierter Umsatz je Monat — gleiche Typ-/Statuslogik wie $invoicedRange.
        $invoiceTypes = ['invoice', 'salesinvoice', 'purchaseinvoice'];
        $voidStatuses = ['voided', 'cancelled'];
        $revenue = array_fill_keys(array_keys($months), 0.0);

        LexofficeVoucher::query()
            ->where('customer_id', $customer->getKey())
            ->where('archived', false)
            ->whereIn('voucher_type', $invoiceTypes)
            ->whereNotIn('voucher_status', $voidStatuses)
            ->whereBetween('voucher_date', [$prevStart->startOfDay(), $end->endOfDay()])
            ->get()
            ->each(function (LexofficeVoucher $v) use (&$revenue, &$prevRevenue): void {
                $ym = $v->voucher_date instanceof \Carbon\CarbonInterface ? $v->voucher_date->format('Y-m') : substr((string) $v->voucher_date, 0, 7);
                $amount = $v->total_amount?->toFloat() ?? 0.0;
                if (isset($revenue[$ym])) {
                    $revenue[$ym] += $amount;
                } elseif (isset($prevRevenue[$ym])) {
                    $prevRevenue[$ym] += $amount;
                }
            });

        if (Gate::allows('viewAny', Invoice::class)) {
            Invoice::query()
                ->where('customer_id', $customer->getKey())
                ->whereIn('type', $invoiceTypes)
                ->whereNotIn('status', $voidStatuses)
                ->whereBetween('issued_on', [$prevStart->toDateString(), $end->toDateString()])
                ->get()
                ->each(function (Invoice $inv) use (&$revenue, &$prevRevenue): void {
                    $ym = $inv->issued_on instanceof \Carbon\CarbonInterface ? $inv->issued_on->format('Y-m') : substr((string) $inv->issued_on, 0, 7);
                    $amount = $inv->total?->toFloat() ?? 0.0;
                    if (isset($revenue[$ym])) {
                        $revenue[$ym] += $amount;
                    } elseif (isset($prevRevenue[$ym])) {
                        $prevRevenue[$ym] += $amount;
                    }
                });
        }

        // Materialkosten je Monat (nach allocated_on) für die Umsatz-Gegenüberstellung.
        $material = array_fill_keys(array_keys($months), 0.0);
        $customer->materialCostAllocations()
            ->whereBetween('allocated_on', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (MaterialCostAllocation $a) use (&$material): void {
                $ym = $a->allocated_on->format('Y-m');
                if (isset($material[$ym])) {
                    $material[$ym] += $a->allocated_amount?->toFloat() ?? 0.0;
                }
            });
        $hasMaterial = array_sum($material) > 0.0;
        $hasPrevHours = array_sum($prevMinutes) > 0;
        $hasPrevRevenue = array_sum($prevRevenue) > 0.0;

        $hours = [];
        $revenueSeries = [];
        foreach ($months as $ym => $m) {
            $label = $m->isoFormat('MMM YY');
            $prevKey = $prevKeyOf[$ym];
            $hourRow = [
                'x' => $label,
                'billable' => round($billableMinutes[$ym] / 60, 1),
                'nonbillable' => round(max(0, $minutes[$ym] - $billableMinutes[$ym]) / 60, 1),
            ];
            // Vorjahres-Gesamtstunden als Vergleichslinie (nur wenn es Vorjahresdaten gibt).
            if ($hasPrevHours) {
                $hourRow['compare'] = round(($prevMinutes[$prevKey] ?? 0) / 60, 1);
            }
            $hours[] = $hourRow;
            $point = [
                'x' => $label,
                'y' => round($revenue[$ym], 2),
            ];
            // Zweitserie (Materialkosten) nur, wenn überhaupt zugeordnet — sonst
            // keine leere Vergleichsspalte.
            if ($hasMaterial) {
                $point['y2'] = round($material[$ym], 2);
            }
            if ($hasPrevRevenue) {
                $point['compare'] = round($prevRevenue[$prevKey] ?? 0.0, 2);
            }
            $revenueSeries[] = $point;
        }

        return ['hours' => $hours, 'revenue' => $revenueSeries];
    }

    public function create(): View {
        Gate::authorize('create', Customer::class);

        return view('customers._form_dialog', [
            'customer' => null,
            'isDialog' => true,
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function store(SaveCustomerRequest $request): RedirectResponse {
        Gate::authorize('create', Customer::class);

        $data = $request->validated();
        $tagIds = $data['tag_ids'] ?? [];
        $newTagsRaw = (string) ($data['new_tags'] ?? '');
        unset($data['tag_ids'], $data['new_tags']);

        $customer = Customer::create($data + ['created_by' => Auth::id()]);
        $customer->syncTagsFromInput($tagIds, \App\Support\TagInput::names($newTagsRaw));

        return redirect()->route('customers.show', $customer)
            ->with('success', __('Kunde angelegt.'));
    }

    public function edit(Customer $customer): View {
        Gate::authorize('update', $customer);

        return view('customers._form_dialog', [
            'customer' => $customer,
            'isDialog' => true,
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function update(SaveCustomerRequest $request, Customer $customer): RedirectResponse {
        Gate::authorize('update', $customer);

        $data = $request->validated();
        $tagIds = $data['tag_ids'] ?? [];
        $newTagsRaw = (string) ($data['new_tags'] ?? '');
        unset($data['tag_ids'], $data['new_tags']);

        // fill() vor save(): getDirty() kennt die Änderungen erst danach.
        $customer->fill($data);
        $changed = array_keys($customer->getDirty());
        $customer->save();
        $customer->syncTagsFromInput($tagIds, \App\Support\TagInput::names($newTagsRaw));

        // Korrigierte Stammdaten zurück an Lexoffice — sonst holt der nächste
        // Abgleich den alten Wert wieder.
        $pushed = app(ContactMasterDataPusher::class)->pushIfLinked($customer, $changed);

        // Abrechenbar-Schalter auf offene Zeiten durchziehen — billable ist am
        // Eintrag ein Snapshot und bliebe sonst auf dem alten Wert stehen.
        $syncedBillable = in_array('billable', $changed, true)
            ? app(\App\Services\Billing\TimeEntryBillableSyncService::class)->syncCustomer($customer)
            : 0;

        $message = $pushed
            ? __('Kunde aktualisiert und an Lexoffice übertragen.')
            : __('Kunde aktualisiert.');
        if ($syncedBillable > 0) {
            $message .= ' ' . trans_choice(':count offener Zeiteintrag an die neue Abrechenbarkeit angepasst.|:count offene Zeiteinträge an die neue Abrechenbarkeit angepasst.', $syncedBillable, ['count' => $syncedBillable]);
        }

        return redirect()->route('customers.show', $customer)
            ->with('success', $message);
    }

    public function destroy(Customer $customer): RedirectResponse {
        Gate::authorize('delete', $customer);

        if ($customer->hasNonDefaultProjects()) {
            return redirect()->route('customers.show', $customer)
                ->with('error', __('Kunde kann nicht gelöscht werden: Es existieren noch Projekte. Bitte zuerst archivieren oder Projekte entfernen.'));
        }

        if ($customer->externalReferences()->exists()) {
            return redirect()->route('customers.show', $customer)
                ->with('error', __('Kunde kann nicht gelöscht werden: Es existieren externe Referenzen (z. B. Lexoffice). Bitte stattdessen archivieren.'));
        }

        // Vollaudit 2026-07 (M9): KI-Gedächtnis auditiert löschen (Einzel-Audit
        // je Eintrag + Provider-Glossar-Hook) statt stiller FK-Kaskade.
        app(\App\Services\Ai\AiMemoryService::class)->deleteForCustomer(
            $customer->organization()->firstOrFail(),
            (int) $customer->id,
        );

        // Standardprojekt(e) zusammen mit dem Kunden entfernen.
        $customer->projects()->where('is_default', true)->delete();
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', __('Kunde gelöscht.'));
    }

    public function archive(Customer $customer): RedirectResponse {
        return $this->archiveModel($customer, __('Kunde archiviert.'));
    }

    public function restore(Customer $customer): RedirectResponse {
        return $this->restoreModel($customer, __('Kunde wiederhergestellt.'));
    }

    /**
     * CSV-Export der aktuell sichtbaren Kunden (Filter & Suche aus Request).
     */
    public function export(Request $request): StreamedResponse {
        Gate::authorize('viewAny', Customer::class);

        $status = $request->string('status')->toString() ?: 'active';
        $search = $request->string('q')->toString();

        $query = Customer::query()
            ->search($search)
            ->when($status === 'active', fn($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn($q) => $q->whereNotNull('archived_at'))
            ->when($status === 'billable_pending', function ($q): void {
                $q->whereNull('archived_at')->withUnexportedBillable();
            })
            ->orderBy('name');

        $filename = 'kunden-' . now()->format('Y-m-d-His') . '.csv';

        $rows = (static function () use ($query): \Generator {
            /** @var Customer $c */
            foreach ($query->lazy(500) as $c) {
                yield [
                    $c->number,
                    $c->name,
                    $c->company,
                    $c->vat_id,
                    $c->email,
                    $c->phone ?: $c->mobile,
                    $c->address_street,
                    $c->address_zip,
                    $c->address_city,
                    $c->country,
                    $c->currency->value,
                    $c->hourly_rate,
                    $c->billable ? 'ja' : 'nein',
                    $c->archived_at?->format('Y-m-d') ?? '',
                    $c->created_at?->format('Y-m-d') ?? '',
                ];
            }
        })();

        return CsvExport::streamFromRows($filename, [
            'Nummer',
            'Name',
            'Firma',
            'USt-IdNr.',
            'E-Mail',
            'Telefon',
            'Straße',
            'PLZ',
            'Ort',
            'Land',
            'Währung',
            'Stundensatz',
            'Abrechenbar',
            'Archiviert',
            'Angelegt',
        ], $rows);
    }

    /**
     * Zeigt das CSV-Import-Formular.
     */
    public function importForm(): View {
        Gate::authorize('viewAny', Customer::class);
        /** @var User|null $authUser */
        $authUser = Auth::user();
        if (! $authUser?->canManageBilling()) {
            abort(403);
        }

        return view('customers.import');
    }

    /**
     * Verarbeitet einen CSV-Upload und legt/aktualisiert Kunden — synchron
     * über die EntitySpec-Pipeline (CustomerSpec, Direkt-Upsert ohne Inbox).
     */
    public function import(Request $request, DirectCsvImportService $importer): RedirectResponse {
        Gate::authorize('viewAny', Customer::class);
        /** @var User|null $authUser */
        $authUser = Auth::user();
        if (! $authUser?->canManageBilling()) {
            abort(403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:' . (int) Setting::get('uploads.csv_import_kb', 10240)],
        ]);

        $file = $request->file('file');
        if ($file === null) {
            return back()->with('error', __('Keine Datei hochgeladen.'));
        }

        $organization = app()->bound('currentOrganization') && app('currentOrganization') instanceof Organization
            ? app('currentOrganization')
            : $authUser->organization;
        abort_unless($organization instanceof Organization, 403);

        $result = $importer->import($file, ImportEntity::Customers, $organization);

        $message = __('CSV-Import: :c angelegt, :u aktualisiert, :s übersprungen.', [
            'c' => $result['created'],
            'u' => $result['updated'],
            's' => $result['skipped'],
        ]);

        if ($result['errors'] !== []) {
            return redirect()->route('customers.index')
                ->with('error', $message . ' Fehler: ' . implode(' | ', array_slice($result['errors'], 0, 5)));
        }

        return redirect()->route('customers.index')->with('success', $message);
    }
}
