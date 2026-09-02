<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EconomicsReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Enums\Expense\ExpenseStatus;
use App\Models\{BillOfQuantity, BoqItem, BoqItemMapping, BoqItemProgress, Customer, Expense, Material, MaterialUsage, Project, TimeEntry, Timesheet, TravelLog};
use App\Services\Gaeb\BoqCalculationDataService;
use App\Services\Travel\TravelChargeService;
use App\Support\ChartBucket;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Feature 014 (Nachkalkulation & Wirtschaftlichkeit): Berechnet die
 * Deckungsbeitrags-/Wirtschaftlichkeitssicht je Projekt und je Kunde aus den
 * ECHTEN Modellfeldern. Reine Auswertung, additiv zu den bestehenden Reports.
 *
 * Erlös-Quellen (abrechenbar):
 *  - TimeEntry.rate   (Snapshot des Abrechnungsbetrags; billable=true)
 *  - MaterialUsage.line_total_net (billed=true; Projektbezug über Timesheet)
 *  - Expense.amount_net (billable=true und freigegeben/erstattet/fakturiert)
 *  - Anfahrt-Konditionen des {@see TravelChargeService} (Projektion über die
 *    Touren des Zeitraums inkl. bereits abgerechneter — Vollaudit 2026-07, M7)
 *
 * Kosten-Quellen (direkte Aufwände):
 *  - TimeEntry.internal_rate (Snapshot des internen Kostensatzes × Zeit;
 *    aufgelöst über User/Task/Project/Customer.internal_rate im RateCalculator)
 *  - MaterialUsage.line_total_net (Einkaufs-/Direktaufwand; es existiert KEIN
 *    separates Materialkosten-Feld → Netto-Wert wird als Direktaufwand geführt)
 *  - Expense.amount_net (Beleg-Direktaufwand)
 *  - TravelLog.reimbursement_total (Fahrt-Erstattungsaufwand im Zeitraum, M7)
 *
 * Deckungsbeitrag = Erlös − direkte Kosten. Da TimeEntry.internal_rate als
 * echter Kostensatz existiert, ist ein voller Deckungsbeitrag (nicht nur DB I)
 * möglich. Fehlt der interne Satz für Einträge, fließen diese mit 0 Kosten ein
 * (→ {@see RowResult::costRateMissing()} markiert die Lücke transparent).
 *
 * Nacharbeit/Kulanz: TimeEntry trägt KEINEN dedizierten „Nacharbeit"-Typ. Als
 * ehrlicher Proxy werden nicht-abrechenbare Zeiten (billable=false) ausgewiesen.
 *
 * Plan-vs-Ist: Project.time_budget (Minuten) und Project.budget (€) als
 * Plan-Werte gegen Ist-Minuten und Ist-Kosten.
 */
class EconomicsReportBuilder {
    public function __construct(private readonly TravelChargeService $travelCharges = new TravelChargeService()) {}

    /**
     * Wirtschaftlichkeit je Projekt im Zeitraum.
     *
     * @param  list<int>|null  $projectIds  Optionaler Filter auf Projekt-IDs.
     * @param  int|null  $customerId  Optionaler Kundenfilter (Feature 002).
     * @param  list<int>  $excludedCustomerIds  Feature 002: Projekte org-weit
     *         ausgeblendeter Kunden entfallen; Übersteuerung regelt der Aufrufer.
     * @return list<array{
     *   projectId:int,
     *   projectName:string,
     *   customerId:int|null,
     *   customerName:string,
     *   billableMinutes:int,
     *   nonBillableMinutes:int,
     *   totalMinutes:int,
     *   nonBillableShare:float,
     *   revenueTime:float,
     *   revenueMaterial:float,
     *   revenueExpense:float,
     *   revenueTravel:float,
     *   revenue:float,
     *   costTime:float,
     *   costMaterial:float,
     *   costExpense:float,
     *   costTravel:float,
     *   cost:float,
     *   contribution:float,
     *   margin:float,
     *   costRateMissing:bool,
     *   reworkMinutes:int,
     *   goodwillMinutes:int,
     *   reworkCost:float,
     *   goodwillCost:float,
     *   reworkShare:float,
     *   planMinutes:int|null,
     *   actualMinutes:int,
     *   planMinutesDelta:int|null,
     *   planBudget:float|null,
     *   actualCost:float,
     *   planBudgetDelta:float|null
     * }>
     */
    public function byProject(CarbonImmutable $from, CarbonImmutable $to, ?array $projectIds = null, ?int $customerId = null, array $excludedCustomerIds = []): array {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $projects = Project::query()
            ->when($projectIds !== null && $projectIds !== [], fn($q) => $q->whereIn('id', $projectIds))
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            // NOT IN würde NULL-Kunden mit verwerfen — kundenlose Projekte bleiben sichtbar.
            ->when($excludedCustomerIds !== [], fn($q) => $q->where(
                fn($w) => $w->whereNull('customer_id')->orWhereNotIn('customer_id', $excludedCustomerIds),
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id', 'time_budget', 'budget']);

        if ($projects->isEmpty()) {
            return [];
        }

        $customers = Customer::query()
            ->whereIn('id', $projects->pluck('customer_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');
        $customerNames = $customers->map(static fn(Customer $c): string => (string) $c->name);

        return array_values($projects
            ->map(function (Project $project) use ($fromDate, $toDate, $customerNames, $customers): array {
                $time = $this->timeAggregate(
                    TimeEntry::query()
                        ->where('project_id', $project->id)
                        ->whereBetween('date', [$fromDate, $toDate])
                );

                $material = $this->materialAggregate($fromDate, $toDate, projectId: (int) $project->id);
                $expense = $this->expenseAggregate($fromDate, $toDate, projectId: (int) $project->id);
                $travel = $this->travelAggregate(
                    $fromDate,
                    $toDate,
                    projectId: (int) $project->id,
                    customer: $customers->get($project->customer_id),
                    project: $project,
                );

                $row = $this->composeRow(
                    $time,
                    $material,
                    $expense,
                    $travel,
                    planMinutes: $project->time_budget !== null ? (int) $project->time_budget : null,
                    planBudget: $project->budget?->toFloat(),
                );

                return array_merge([
                    'projectId' => (int) $project->id,
                    'projectName' => (string) $project->name,
                    'customerId' => $project->customer_id !== null ? (int) $project->customer_id : null,
                    'customerName' => (string) ($customerNames[$project->customer_id] ?? '—'),
                ], $row);
            })
            ->all());
    }

    /**
     * Wirtschaftlichkeit je Kunde im Zeitraum (über alle Kunden-Projekte sowie
     * direkt am Kunden hängende Spesen).
     *
     * Feature 002: optionaler Kunden-/Projektfilter. Mit Projektfilter werden
     * die Geld-Aggregate projektgebunden erhoben (direkt am Kunden hängende
     * Spesen/Fahrten ohne Projektanker bleiben dann bewusst außen vor).
     *
     * @param  list<int>  $excludedCustomerIds  Feature 002: org-weit ausgeblendete
     *         Kunden entfallen als Zeilen; Übersteuerung regelt der Aufrufer.
     * @return list<array{
     *   customerId:int,
     *   customerName:string,
     *   billableMinutes:int,
     *   nonBillableMinutes:int,
     *   totalMinutes:int,
     *   nonBillableShare:float,
     *   revenueTime:float,
     *   revenueMaterial:float,
     *   revenueExpense:float,
     *   revenueTravel:float,
     *   revenue:float,
     *   costTime:float,
     *   costMaterial:float,
     *   costExpense:float,
     *   costTravel:float,
     *   cost:float,
     *   contribution:float,
     *   margin:float,
     *   costRateMissing:bool,
     *   reworkMinutes:int,
     *   goodwillMinutes:int,
     *   reworkCost:float,
     *   goodwillCost:float,
     *   reworkShare:float,
     *   planMinutes:int|null,
     *   actualMinutes:int,
     *   planMinutesDelta:int|null,
     *   planBudget:float|null,
     *   actualCost:float,
     *   planBudgetDelta:float|null
     * }>
     */
    public function byCustomer(CarbonImmutable $from, CarbonImmutable $to, ?int $customerId = null, ?int $projectId = null, array $excludedCustomerIds = []): array {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $customers = Customer::query()
            ->when($customerId !== null, fn($q) => $q->whereKey($customerId))
            ->when($excludedCustomerIds !== [], fn($q) => $q->whereNotIn('id', $excludedCustomerIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Projekte (inkl. Budget) einmal laden + nach Kunde gruppieren statt N×3 Queries; Geld-Aggregate bleiben
        // kundenweise, um die Semantik der Projekt-/Kundenfilter unverändert zu lassen.
        $projectsByCustomer = Project::query()
            ->when($projectId !== null, fn($q) => $q->whereKey($projectId))
            ->get(['id', 'customer_id', 'time_budget', 'budget'])
            ->groupBy(static fn(Project $p): int => (int) $p->customer_id);

        return array_values($customers
            ->map(function (Customer $customer) use ($fromDate, $toDate, $projectsByCustomer, $projectId): array {
                /** @var \Illuminate\Support\Collection<int, Project> $customerProjects */
                $customerProjects = $projectsByCustomer->get((int) $customer->id, collect());

                $projectIds = array_values($customerProjects->pluck('id')->map(static fn($v): int => (int) $v)->all());

                $time = $this->timeAggregate(
                    TimeEntry::query()
                        ->whereBetween('date', [$fromDate, $toDate])
                        ->when($projectIds !== [], fn($q) => $q->whereIn('project_id', $projectIds), fn($q) => $q->whereRaw('1=0'))
                );

                $material = $this->materialAggregate($fromDate, $toDate, projectIds: $projectIds);
                if ($projectId !== null) {
                    // Projektfilter: strikt projektgebundene Aggregate — Spesen/
                    // Fahrten, die nur am Kunden hängen, zählen hier nicht mit.
                    $projectModel = $customerProjects->firstWhere('id', $projectId);
                    $expense = $this->expenseAggregate($fromDate, $toDate, projectIds: $projectIds);
                    $travel = $projectModel instanceof Project
                        ? $this->travelAggregate($fromDate, $toDate, projectId: $projectId, customer: $customer, project: $projectModel)
                        : ['revenue' => 0.0, 'cost' => 0.0];
                } else {
                    $expense = $this->expenseAggregate($fromDate, $toDate, customerId: (int) $customer->id, projectIds: $projectIds);
                    $travel = $this->travelAggregate($fromDate, $toDate, customerId: (int) $customer->id, customer: $customer, projectIds: $projectIds);
                }

                $planMinutes = $customerProjects->sum(static fn(Project $p): int => (int) $p->time_budget);
                $planBudget = $customerProjects->sum(static fn(Project $p): float => ($p->budget?->toFloat() ?? 0.0));

                $row = $this->composeRow(
                    $time,
                    $material,
                    $expense,
                    $travel,
                    planMinutes: $planMinutes > 0 ? (int) $planMinutes : null,
                    planBudget: $planBudget > 0 ? (float) $planBudget : null,
                );

                return array_merge([
                    'customerId' => (int) $customer->id,
                    'customerName' => (string) $customer->name,
                ], $row);
            })
            ->filter(static fn(array $r): bool => $r['totalMinutes'] > 0 || $r['revenue'] > 0.0 || $r['cost'] > 0.0)
            ->values()
            ->all());
    }

    /**
     * Aggregiert eine TimeEntry-Query zu Minuten, Erlös und internen Kosten.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TimeEntry>  $query
     * @return array{
     *   billableMinutes:int, nonBillableMinutes:int, totalMinutes:int,
     *   revenue:float, cost:float, costRateMissing:bool,
     *   reworkMinutes:int, goodwillMinutes:int, reworkCost:float, goodwillCost:float
     * }
     */
    private function timeAggregate($query): array {
        /** @var Collection<int, TimeEntry> $entries */
        $entries = $query->get(['minutes', 'billable', 'rate', 'internal_rate', 'rework_reason_classification_id', 'goodwill_reason_classification_id']);

        $billableMinutes = 0;
        $nonBillableMinutes = 0;
        $revenue = 0.0;
        $cost = 0.0;
        $costRateMissing = false;
        // Rang 59a: klassifizierte Nacharbeit/Kulanz getrennt ausweisen.
        $reworkMinutes = 0;
        $goodwillMinutes = 0;
        $reworkCost = 0.0;
        $goodwillCost = 0.0;

        foreach ($entries as $e) {
            $minutes = (int) $e->minutes;
            if ($e->billable) {
                $billableMinutes += $minutes;
                $revenue += ($e->rate?->toFloat() ?? 0.0);
            } else {
                $nonBillableMinutes += $minutes;
            }

            $internal = ($e->internal_rate?->toFloat() ?? 0.0);
            $cost += $internal;
            if ($minutes > 0 && $internal <= 0.0) {
                $costRateMissing = true;
            }

            if ($e->rework_reason_classification_id !== null) {
                $reworkMinutes += $minutes;
                $reworkCost += $internal;
            }
            if ($e->goodwill_reason_classification_id !== null) {
                $goodwillMinutes += $minutes;
                $goodwillCost += $internal;
            }
        }

        return [
            'billableMinutes' => $billableMinutes,
            'nonBillableMinutes' => $nonBillableMinutes,
            'totalMinutes' => $billableMinutes + $nonBillableMinutes,
            'revenue' => round($revenue, 2),
            'cost' => round($cost, 2),
            'costRateMissing' => $costRateMissing,
            'reworkMinutes' => $reworkMinutes,
            'goodwillMinutes' => $goodwillMinutes,
            'reworkCost' => round($reworkCost, 2),
            'goodwillCost' => round($goodwillCost, 2),
        ];
    }

    /**
     * Material-Erlös (billed=true) und Direktaufwand (alle) je Projektbezug.
     * Projektbezug läuft über das Timesheet.
     *
     * @param  list<int>|null  $projectIds
     * @return array{revenue:float, cost:float}
     */
    private function materialAggregate(string $from, string $to, ?int $projectId = null, ?array $projectIds = null): array {
        $timesheetIds = Timesheet::query()
            ->whereBetween('work_date', [$from, $to])
            ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId))
            ->when($projectIds !== null, fn($q) => $projectIds === [] ? $q->whereRaw('1=0') : $q->whereIn('project_id', $projectIds))
            ->pluck('id')
            ->all();

        if ($timesheetIds === []) {
            return ['revenue' => 0.0, 'cost' => 0.0];
        }

        $base = MaterialUsage::query()->whereIn('timesheet_id', $timesheetIds);

        $revenue = (float) $base->clone()->where('billed', true)->sum('line_total_net');
        $cost = (float) $base->clone()->sum('line_total_net');

        return ['revenue' => round($revenue, 2), 'cost' => round($cost, 2)];
    }

    /**
     * Spesen-Erlös (billable=true, freigegeben/erstattet/fakturiert) und
     * Direktaufwand (alle erstattungsfähigen) je Projekt- oder Kundenbezug.
     *
     * @param  list<int>|null  $projectIds
     * @return array{revenue:float, cost:float}
     */
    private function expenseAggregate(
        string $from,
        string $to,
        ?int $projectId = null,
        ?int $customerId = null,
        ?array $projectIds = null
    ): array {
        $settled = [
            ExpenseStatus::Approved->value,
            ExpenseStatus::Reimbursed->value,
            ExpenseStatus::Invoiced->value,
        ];

        $base = Expense::query()
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', $settled);

        if ($projectId !== null) {
            $base->where('project_id', $projectId);
        } elseif ($customerId !== null) {
            $base->where(function ($q) use ($customerId, $projectIds): void {
                $q->where('customer_id', $customerId);
                if ($projectIds !== null && $projectIds !== []) {
                    $q->orWhereIn('project_id', $projectIds);
                }
            });
        } elseif ($projectIds !== null) {
            $projectIds === [] ? $base->whereRaw('1=0') : $base->whereIn('project_id', $projectIds);
        }

        $revenue = (float) $base->clone()->where('billable', true)->sum('amount_net');
        $cost = (float) $base->clone()->sum('amount_net');

        return ['revenue' => round($revenue, 2), 'cost' => round($cost, 2)];
    }

    /**
     * Fahrt-Dimension (Vollaudit 2026-07, M7): Kosten = Erstattungsaufwand aus
     * TravelLog.reimbursement_total im Zeitraum (Projekt- bzw. Kunden-Anker;
     * Fahrten ohne Anker fließen bewusst nirgends ein — kein stilles Raten).
     * Erlös = Projektion der Anfahrt-Konditionen des {@see TravelChargeService}
     * über ALLE Touren des Zeitraums (inkl. bereits abgerechneter), analog zur
     * Material-Logik „abrechenbar = Erlös". Kunden ohne aktivierte
     * Anfahrt-Konditionen tragen 0 Erlös.
     *
     * @param  list<int>|null  $projectIds
     * @return array{revenue:float, cost:float}
     */
    private function travelAggregate(
        string $from,
        string $to,
        ?int $projectId = null,
        ?int $customerId = null,
        ?Customer $customer = null,
        ?Project $project = null,
        ?array $projectIds = null,
    ): array {
        $base = TravelLog::query()->whereBetween('date', [$from, $to]);
        if ($projectId !== null) {
            $base->where('project_id', $projectId);
        } elseif ($customerId !== null) {
            $base->where(function ($q) use ($customerId, $projectIds): void {
                $q->where('customer_id', $customerId);
                if ($projectIds !== null && $projectIds !== []) {
                    $q->orWhereIn('project_id', $projectIds);
                }
            });
        }
        $cost = (float) $base->where('reimbursable', true)->sum('reimbursement_total');

        $revenue = 0.0;
        if ($customer instanceof Customer) {
            $revenue = (float) $this->travelCharges
                ->chargesForRange($customer, $project, ['from' => $from, 'to' => $to], null, pureMaterialOnly: false, includeBilled: true)
                ->sum(static fn(\App\Services\Travel\TravelCharge $c): float => $c->amount());
        }

        return ['revenue' => round($revenue, 2), 'cost' => round($cost, 2)];
    }

    /**
     * Setzt eine Ergebniszeile aus den vier Aggregaten zusammen.
     *
     * @param  array{billableMinutes:int, nonBillableMinutes:int, totalMinutes:int, revenue:float, cost:float, costRateMissing:bool, reworkMinutes:int, goodwillMinutes:int, reworkCost:float, goodwillCost:float}  $time
     * @param  array{revenue:float, cost:float}  $material
     * @param  array{revenue:float, cost:float}  $expense
     * @param  array{revenue:float, cost:float}  $travel
     * @return array{
     *   billableMinutes:int, nonBillableMinutes:int, totalMinutes:int, nonBillableShare:float,
     *   revenueTime:float, revenueMaterial:float, revenueExpense:float, revenueTravel:float, revenue:float,
     *   costTime:float, costMaterial:float, costExpense:float, costTravel:float, cost:float,
     *   contribution:float, margin:float, costRateMissing:bool,
     *   reworkMinutes:int, goodwillMinutes:int, reworkCost:float, goodwillCost:float, reworkShare:float,
     *   planMinutes:int|null, actualMinutes:int, planMinutesDelta:int|null,
     *   planBudget:float|null, actualCost:float, planBudgetDelta:float|null
     * }
     */
    private function composeRow(array $time, array $material, array $expense, array $travel, ?int $planMinutes, ?float $planBudget): array {
        $revenue = round($time['revenue'] + $material['revenue'] + $expense['revenue'] + $travel['revenue'], 2);
        $cost = round($time['cost'] + $material['cost'] + $expense['cost'] + $travel['cost'], 2);
        $contribution = round($revenue - $cost, 2);
        $margin = $revenue > 0.0 ? round(($contribution / $revenue) * 100, 2) : 0.0;

        $totalMinutes = $time['totalMinutes'];
        $nonBillableShare = $totalMinutes > 0
            ? round(($time['nonBillableMinutes'] / $totalMinutes) * 100, 2)
            : 0.0;

        $actualMinutes = $totalMinutes;
        $actualCost = $cost;

        return [
            'billableMinutes' => $time['billableMinutes'],
            'nonBillableMinutes' => $time['nonBillableMinutes'],
            'totalMinutes' => $totalMinutes,
            'nonBillableShare' => $nonBillableShare,
            'revenueTime' => $time['revenue'],
            'revenueMaterial' => $material['revenue'],
            'revenueExpense' => $expense['revenue'],
            'revenueTravel' => $travel['revenue'],
            'revenue' => $revenue,
            'costTime' => $time['cost'],
            'costMaterial' => $material['cost'],
            'costExpense' => $expense['cost'],
            'costTravel' => $travel['cost'],
            'cost' => $cost,
            'contribution' => $contribution,
            'margin' => $margin,
            'costRateMissing' => $time['costRateMissing'],
            'reworkMinutes' => $time['reworkMinutes'],
            'goodwillMinutes' => $time['goodwillMinutes'],
            'reworkCost' => $time['reworkCost'],
            'goodwillCost' => $time['goodwillCost'],
            'reworkShare' => $totalMinutes > 0 ? round(($time['reworkMinutes'] / $totalMinutes) * 100, 2) : 0.0,
            'planMinutes' => $planMinutes,
            'actualMinutes' => $actualMinutes,
            'planMinutesDelta' => $planMinutes !== null ? $actualMinutes - $planMinutes : null,
            'planBudget' => $planBudget,
            'actualCost' => $actualCost,
            'planBudgetDelta' => $planBudget !== null ? round($actualCost - $planBudget, 2) : null,
        ];
    }

    /**
     * Zeit-Erlös (abrechenbare rate-Snapshots) und Zeit-Kosten (internal_rate)
     * je Monat des Zeitraums (Feature 002, Monats-Chart). Bewusst NUR die
     * Zeit-Dimension mit einer Query — Material/Spesen/Fahrt haben keine
     * gemeinsame Monatsquelle ohne weitere Aggregationsläufe.
     *
     * @param  list<int>  $excludedCustomerIds  Feature 002: Zeiten auf Projekten
     *         ausgeblendeter Kunden entfallen; Übersteuerung regelt der Aufrufer.
     * @return list<array{month: string, monthLabel: string, revenue: float, cost: float}>
     */
    public function timeByMonth(CarbonImmutable $from, CarbonImmutable $to, string $unit, ?int $customerId = null, ?int $projectId = null, array $excludedCustomerIds = []): array {
        /** @var Collection<int, TimeEntry> $entries */
        $entries = TimeEntry::query()
            ->whereBetween('date', DateRange::days($from, $to))
            ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId))
            ->when($projectId === null && $customerId !== null, fn($q) => $q->whereIn(
                'project_id',
                Project::query()->where('customer_id', $customerId)->select('id'),
            ))
            ->when($projectId === null && $customerId === null && $excludedCustomerIds !== [], fn($q) => $q->whereNotIn(
                'project_id',
                Project::query()->whereIn('customer_id', $excludedCustomerIds)->select('id'),
            ))
            ->get(['date', 'billable', 'rate', 'internal_rate']);

        $granularity = ChartBucket::granularity($unit, $from, $to);
        if ($granularity === 'hour') {
            $granularity = 'day';
        }

        /** @var array<string, array{revenue: float, cost: float}> $byKey */
        $byKey = [];
        foreach ($entries as $entry) {
            if ($entry->date === null) {
                continue;
            }
            $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse($entry->date->toDateString()))[0];
            $byKey[$key] ??= ['revenue' => 0.0, 'cost' => 0.0];
            if ($entry->billable) {
                $byKey[$key]['revenue'] += ($entry->rate?->toFloat() ?? 0.0);
            }
            $byKey[$key]['cost'] += ($entry->internal_rate?->toFloat() ?? 0.0);
        }

        /** @var array<string, true> $seen */
        $seen = [];
        $series = [];
        for ($cursor = $from->startOfDay(); $cursor->lte($to); $cursor = $cursor->addDay()) {
            [$key, $label] = ChartBucket::keyLabel($granularity, $cursor);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $series[] = [
                'month' => $key,
                'monthLabel' => $label,
                'revenue' => round($byKey[$key]['revenue'] ?? 0.0, 2),
                'cost' => round($byKey[$key]['cost'] ?? 0.0, 2),
            ];
        }

        return $series;
    }

    /**
     * LV-Dimension (MVP-332, Feature 014 × 049): Kosten/Erlöse je LV-Position
     * (Ordnungszahl) eines Projekts mit Leistungsverzeichnis.
     *
     * Erlös je Position = im Zeitraum aufgemessene Menge (BoqItemProgress) ×
     * Einheitspreis — die Projektion der Abrechnung nach Aufmaß (vgl.
     * {@see \App\Services\Gaeb\BoqCostingService}); nur mengen-/preisbasiert
     * abrechenbare Positionsarten mit gepflegtem EP tragen Erlös.
     *
     * Kosten je Position werden über die ECHTEN Verknüpfungen zugeordnet:
     *  - TimeEntry → Bautagebuch-Eintrag → Aufmaß-Meldung (diary_entry_id),
     *  - MaterialUsage → direkte Aufmaß-Verknüpfung (material_usage_id) bzw.
     *    Positions-Mapping des Materialstamms (BoqItemMapping).
     * Mehrdeutige Verknüpfungen (Quellposten zeigt auf mehrere Positionen)
     * werden NICHT still aufgeteilt, sondern als „ohne Zuordnung" geführt —
     * ebenso Quellposten ganz ohne LV-Bezug (u. a. alle Spesen, denen das
     * Datenmodell keinen LV-Anker gibt). Invariante: Positions-Kosten +
     * „ohne Zuordnung" = Projektkosten aus {@see byProject()} (keine stille
     * Lücke).
     *
     * @return array{
     *   hasBoq: bool,
     *   positions: list<array{
     *     boqItemId:int, billId:int, billName:string, referenceNo:string,
     *     shortText:string|null, isAddendum:bool, unit:string|null,
     *     unitPrice:float|null, measuredQuantity:float, revenue:float,
     *     timeMinutes:int, costTime:float, costMaterial:float, cost:float,
     *     contribution:float, calculated:float|null, calcDelta:float|null
     *   }>,
     *   unassigned: array{timeMinutes:int, costTime:float, costMaterial:float, costExpense:float, cost:float},
     *   hasCalculation: bool,
     *   calculationImported: bool
     * }
     */
    public function byBoqPosition(CarbonImmutable $from, CarbonImmutable $to, int $projectId): array {
        $unassigned = ['timeMinutes' => 0, 'costTime' => 0.0, 'costMaterial' => 0.0, 'costExpense' => 0.0, 'cost' => 0.0];

        $bills = BillOfQuantity::query()
            ->where('project_id', $projectId)
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($bills->isEmpty()) {
            return ['hasBoq' => false, 'positions' => [], 'unassigned' => $unassigned, 'hasCalculation' => false, 'calculationImported' => false];
        }

        $billNames = $bills->pluck('name', 'id');

        /** @var Collection<int, BoqItem> $items */
        $items = BoqItem::query()
            ->whereIn('bill_of_quantity_id', $bills->pluck('id')->all())
            ->orderBy('bill_of_quantity_id')
            ->orderBy('position')
            ->get(['id', 'bill_of_quantity_id', 'reference_no', 'short_text', 'type', 'unit', 'unit_price', 'is_addendum', 'position']);

        $itemIds = $items->pluck('id')->map(static fn($v): int => (int) $v)->all();

        // Kalkulierte Kosten je Mengeneinheit aus den GAEB-Kalkulationsdaten
        // (X52, Feature 109) - der Plan-Wert des Plan-Ist-Vergleichs. Die
        // Herkunft reist mit: Eine importierte Fremdkalkulation ist die
        // Rechnung eines anderen Betriebs, nicht die eigene Planung.
        $calculationService = app(BoqCalculationDataService::class);
        $unitCalcCosts = [];
        $calculationImported = false;
        foreach ($bills as $bill) {
            $billItemIds = array_values($items->where('bill_of_quantity_id', $bill->id)->pluck('id')->map(static fn ($v): int => (int) $v)->all());
            $unitCalcCosts += $calculationService->unitCostsFor($bill, $billItemIds);
            $calculationImported = $calculationImported || $calculationService->calculationIsImported($bill);
        }

        // Aufmaß im Zeitraum (Erlös-Basis) je Position.
        $measured = BoqItemProgress::query()
            ->whereIn('boq_item_id', $itemIds)
            ->whereBetween('captured_at', [$from, $to])
            ->selectRaw('boq_item_id, SUM(quantity) AS measured')
            ->groupBy('boq_item_id')
            ->pluck('measured', 'boq_item_id');

        // Strukturelle Zuordnungs-Verknüpfungen (bewusst NICHT periodengefiltert; Zeitraum via Quellposten eingegrenzt).
        $diaryToItems = [];
        $usageToItems = [];
        BoqItemProgress::query()
            ->whereIn('boq_item_id', $itemIds)
            ->where(static function ($q): void {
                $q->whereNotNull('diary_entry_id')->orWhereNotNull('material_usage_id');
            })
            ->get(['boq_item_id', 'diary_entry_id', 'material_usage_id'])
            ->each(static function (BoqItemProgress $link) use (&$diaryToItems, &$usageToItems): void {
                if ($link->diary_entry_id !== null) {
                    $diaryToItems[(int) $link->diary_entry_id][(int) $link->boq_item_id] = true;
                }
                if ($link->material_usage_id !== null) {
                    $usageToItems[(int) $link->material_usage_id][(int) $link->boq_item_id] = true;
                }
            });

        $materialToItems = [];
        BoqItemMapping::query()
            ->whereIn('boq_item_id', $itemIds)
            ->where('mappable_type', Material::class)
            ->get(['boq_item_id', 'mappable_id'])
            ->each(static function (BoqItemMapping $mapping) use (&$materialToItems): void {
                $materialToItems[(int) $mapping->mappable_id][(int) $mapping->boq_item_id] = true;
            });

        // Kosten-Sammler je Position.
        $timeMinutes = array_fill_keys($itemIds, 0);
        $costTime = array_fill_keys($itemIds, 0.0);
        $costMaterial = array_fill_keys($itemIds, 0.0);

        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        /** @var Collection<int, TimeEntry> $entries */
        $entries = TimeEntry::query()
            ->where('project_id', $projectId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->get(['minutes', 'internal_rate', 'diary_entry_id']);

        foreach ($entries as $entry) {
            $itemId = $entry->diary_entry_id !== null
                ? $this->uniqueTarget($diaryToItems[(int) $entry->diary_entry_id] ?? [])
                : null;
            if ($itemId !== null) {
                $timeMinutes[$itemId] += (int) $entry->minutes;
                $costTime[$itemId] += ($entry->internal_rate?->toFloat() ?? 0.0);
            } else {
                $unassigned['timeMinutes'] += (int) $entry->minutes;
                $unassigned['costTime'] += ($entry->internal_rate?->toFloat() ?? 0.0);
            }
        }

        $timesheetIds = Timesheet::query()
            ->where('project_id', $projectId)
            ->whereBetween('work_date', [$fromDate, $toDate])
            ->pluck('id')
            ->all();

        if ($timesheetIds !== []) {
            /** @var Collection<int, MaterialUsage> $usages */
            $usages = MaterialUsage::query()
                ->whereIn('timesheet_id', $timesheetIds)
                ->get(['id', 'material_id', 'line_total_net']);

            foreach ($usages as $usage) {
                $itemId = $this->uniqueTarget($usageToItems[(int) $usage->id] ?? [])
                    ?? ($usage->material_id !== null ? $this->uniqueTarget($materialToItems[(int) $usage->material_id] ?? []) : null);
                if ($itemId !== null) {
                    $costMaterial[$itemId] += $usage->line_total_net?->toFloat() ?? 0.0;
                } else {
                    $unassigned['costMaterial'] += $usage->line_total_net?->toFloat() ?? 0.0;
                }
            }
        }

        // Spesen tragen keinen LV-Anker → vollständig „ohne Zuordnung" (wie expenseAggregate je Projekt).
        $unassigned['costExpense'] = $this->expenseAggregate($fromDate, $toDate, projectId: $projectId)['cost'];

        $unassigned['costTime'] = round($unassigned['costTime'], 2);
        $unassigned['costMaterial'] = round($unassigned['costMaterial'], 2);
        $unassigned['cost'] = round($unassigned['costTime'] + $unassigned['costMaterial'] + $unassigned['costExpense'], 2);

        $positions = [];
        foreach ($items as $item) {
            $id = (int) $item->id;
            $quantity = (float) ($measured[$id] ?? 0.0);
            $unitPrice = $item->unit_price?->toFloat();
            $revenue = $item->type->isBillable() && $unitPrice !== null
                ? round($quantity * $unitPrice, 2)
                : 0.0;
            $cost = round($costTime[$id] + $costMaterial[$id], 2);

            // Nur Positionen mit Bewegung im Zeitraum — ein LV kann hunderte
            // unberührte Positionen tragen, die den Report nur verwässern.
            if ($quantity === 0.0 && $cost === 0.0 && $timeMinutes[$id] === 0) {
                continue;
            }

            // Kalkuliert wurde die volle LV-Menge; verglichen wird mit dem,
            // was bisher ausgeführt ist - sonst sähe jeder unfertige Abschnitt
            // wie eine Ersparnis aus.
            $calculated = isset($unitCalcCosts[$id]) ? round($unitCalcCosts[$id] * $quantity, 2) : null;

            $positions[] = [
                'boqItemId' => $id,
                'billId' => (int) $item->bill_of_quantity_id,
                'billName' => (string) ($billNames[$item->bill_of_quantity_id] ?? '—'),
                'referenceNo' => (string) $item->reference_no,
                'shortText' => $item->short_text,
                'isAddendum' => (bool) $item->is_addendum,
                'unit' => $item->unit,
                'unitPrice' => $unitPrice,
                'measuredQuantity' => round($quantity, 4),
                'revenue' => $revenue,
                'timeMinutes' => $timeMinutes[$id],
                'costTime' => round($costTime[$id], 2),
                'costMaterial' => round($costMaterial[$id], 2),
                'cost' => $cost,
                'contribution' => round($revenue - $cost, 2),
                'calculated' => $calculated,
                // Ohne Kalkulation gibt es nichts zu vergleichen - null, nicht 0 €.
                'calcDelta' => $calculated === null ? null : round($cost - $calculated, 2),
            ];
        }

        return [
            'hasBoq' => true,
            'positions' => $positions,
            'unassigned' => $unassigned,
            'hasCalculation' => $unitCalcCosts !== [],
            'calculationImported' => $calculationImported,
        ];
    }

    /**
     * Genau EIN Zuordnungsziel → dessen ID, sonst null (mehrdeutige
     * Verknüpfungen werden nicht still aufgeteilt).
     *
     * @param  array<int, true>  $targets
     */
    private function uniqueTarget(array $targets): ?int {
        return count($targets) === 1 ? array_key_first($targets) : null;
    }
}
