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
use App\Models\{Customer, Expense, MaterialUsage, Project, TimeEntry, Timesheet};
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
 *
 * Kosten-Quellen (direkte Aufwände):
 *  - TimeEntry.internal_rate (Snapshot des internen Kostensatzes × Zeit;
 *    aufgelöst über User/Task/Project/Customer.internal_rate im RateCalculator)
 *  - MaterialUsage.line_total_net (Einkaufs-/Direktaufwand; es existiert KEIN
 *    separates Materialkosten-Feld → Netto-Wert wird als Direktaufwand geführt)
 *  - Expense.amount_net (Beleg-Direktaufwand)
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
    /**
     * Wirtschaftlichkeit je Projekt im Zeitraum.
     *
     * @param  list<int>|null  $projectIds  Optionaler Filter auf Projekt-IDs.
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
     *   revenue:float,
     *   costTime:float,
     *   costMaterial:float,
     *   costExpense:float,
     *   cost:float,
     *   contribution:float,
     *   margin:float,
     *   costRateMissing:bool,
     *   planMinutes:int|null,
     *   actualMinutes:int,
     *   planMinutesDelta:int|null,
     *   planBudget:float|null,
     *   actualCost:float,
     *   planBudgetDelta:float|null
     * }>
     */
    public function byProject(CarbonImmutable $from, CarbonImmutable $to, ?array $projectIds = null): array {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $projects = Project::query()
            ->when($projectIds !== null && $projectIds !== [], fn($q) => $q->whereIn('id', $projectIds))
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id', 'time_budget', 'budget']);

        if ($projects->isEmpty()) {
            return [];
        }

        $customerNames = Customer::query()
            ->whereIn('id', $projects->pluck('customer_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        return array_values($projects
            ->map(function (Project $project) use ($fromDate, $toDate, $customerNames): array {
                $time = $this->timeAggregate(
                    TimeEntry::query()
                        ->where('project_id', $project->id)
                        ->whereBetween('date', [$fromDate, $toDate])
                );

                $material = $this->materialAggregate($fromDate, $toDate, projectId: (int) $project->id);
                $expense = $this->expenseAggregate($fromDate, $toDate, projectId: (int) $project->id);

                $row = $this->composeRow(
                    $time,
                    $material,
                    $expense,
                    planMinutes: $project->time_budget !== null ? (int) $project->time_budget : null,
                    planBudget: $project->budget !== null ? (float) $project->budget : null,
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
     *   revenue:float,
     *   costTime:float,
     *   costMaterial:float,
     *   costExpense:float,
     *   cost:float,
     *   contribution:float,
     *   margin:float,
     *   costRateMissing:bool,
     *   planMinutes:int|null,
     *   actualMinutes:int,
     *   planMinutesDelta:int|null,
     *   planBudget:float|null,
     *   actualCost:float,
     *   planBudgetDelta:float|null
     * }>
     */
    public function byCustomer(CarbonImmutable $from, CarbonImmutable $to): array {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $customers = Customer::query()->orderBy('name')->get(['id', 'name']);

        // Projekte (inkl. Budget-Felder) einmal laden und nach Kunde gruppieren,
        // statt pro Kunde je drei Queries (Projekt-IDs + zwei Budget-Summen) zu
        // feuern. Die Geld-Aggregate bleiben kundenweise, um die Semantik der
        // Projekt-/Kundenfilter unverändert zu lassen.
        $projectsByCustomer = Project::query()
            ->get(['id', 'customer_id', 'time_budget', 'budget'])
            ->groupBy(static fn (Project $p): int => (int) $p->customer_id);

        return array_values($customers
            ->map(function (Customer $customer) use ($fromDate, $toDate, $projectsByCustomer): array {
                /** @var \Illuminate\Support\Collection<int, Project> $customerProjects */
                $customerProjects = $projectsByCustomer->get((int) $customer->id, collect());

                $projectIds = $customerProjects->pluck('id')->map(static fn ($v): int => (int) $v)->all();

                $time = $this->timeAggregate(
                    TimeEntry::query()
                        ->whereBetween('date', [$fromDate, $toDate])
                        ->when($projectIds !== [], fn($q) => $q->whereIn('project_id', $projectIds), fn($q) => $q->whereRaw('1=0'))
                );

                $material = $this->materialAggregate($fromDate, $toDate, projectIds: $projectIds);
                $expense = $this->expenseAggregate($fromDate, $toDate, customerId: (int) $customer->id, projectIds: $projectIds);

                $planMinutes = $customerProjects->sum(static fn (Project $p): int => (int) $p->time_budget);
                $planBudget = $customerProjects->sum(static fn (Project $p): float => (float) $p->budget);

                $row = $this->composeRow(
                    $time,
                    $material,
                    $expense,
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
     *   revenue:float, cost:float, costRateMissing:bool
     * }
     */
    private function timeAggregate($query): array {
        /** @var Collection<int, TimeEntry> $entries */
        $entries = $query->get(['minutes', 'billable', 'rate', 'internal_rate']);

        $billableMinutes = 0;
        $nonBillableMinutes = 0;
        $revenue = 0.0;
        $cost = 0.0;
        $costRateMissing = false;

        foreach ($entries as $e) {
            $minutes = (int) $e->minutes;
            if ($e->billable) {
                $billableMinutes += $minutes;
                $revenue += (float) $e->rate;
            } else {
                $nonBillableMinutes += $minutes;
            }

            $internal = (float) $e->internal_rate;
            $cost += $internal;
            if ($minutes > 0 && $internal <= 0.0) {
                $costRateMissing = true;
            }
        }

        return [
            'billableMinutes' => $billableMinutes,
            'nonBillableMinutes' => $nonBillableMinutes,
            'totalMinutes' => $billableMinutes + $nonBillableMinutes,
            'revenue' => round($revenue, 2),
            'cost' => round($cost, 2),
            'costRateMissing' => $costRateMissing,
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
     * Setzt eine Ergebniszeile aus den drei Aggregaten zusammen.
     *
     * @param  array{billableMinutes:int, nonBillableMinutes:int, totalMinutes:int, revenue:float, cost:float, costRateMissing:bool}  $time
     * @param  array{revenue:float, cost:float}  $material
     * @param  array{revenue:float, cost:float}  $expense
     * @return array{
     *   billableMinutes:int, nonBillableMinutes:int, totalMinutes:int, nonBillableShare:float,
     *   revenueTime:float, revenueMaterial:float, revenueExpense:float, revenue:float,
     *   costTime:float, costMaterial:float, costExpense:float, cost:float,
     *   contribution:float, margin:float, costRateMissing:bool,
     *   planMinutes:int|null, actualMinutes:int, planMinutesDelta:int|null,
     *   planBudget:float|null, actualCost:float, planBudgetDelta:float|null
     * }
     */
    private function composeRow(array $time, array $material, array $expense, ?int $planMinutes, ?float $planBudget): array {
        $revenue = round($time['revenue'] + $material['revenue'] + $expense['revenue'], 2);
        $cost = round($time['cost'] + $material['cost'] + $expense['cost'], 2);
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
            'revenue' => $revenue,
            'costTime' => $time['cost'],
            'costMaterial' => $material['cost'],
            'costExpense' => $expense['cost'],
            'cost' => $cost,
            'contribution' => $contribution,
            'margin' => $margin,
            'costRateMissing' => $time['costRateMissing'],
            'planMinutes' => $planMinutes,
            'actualMinutes' => $actualMinutes,
            'planMinutesDelta' => $planMinutes !== null ? $actualMinutes - $planMinutes : null,
            'planBudget' => $planBudget,
            'actualCost' => $actualCost,
            'planBudgetDelta' => $planBudget !== null ? round($actualCost - $planBudget, 2) : null,
        ];
    }
}
