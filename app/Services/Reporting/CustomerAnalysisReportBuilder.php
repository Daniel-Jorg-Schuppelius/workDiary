<?php
/*
 * Created on   : Mon Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerAnalysisReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Models\{Customer, DiaryEntry, OpenIssue, Project, Protocol, TimeEntry};
use App\Support\ChartBucket;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Kundenanalyse-Kennzahlen (MVP-039): Aufwand, abrechenbare und nicht
 * abrechenbare Zeit, Nacharbeit, offene Punkte und 30-Tage-Trend je Kunde.
 *
 * Die reine Datenaufbereitung liegt bewusst getrennt vom Controller (HTTP-
 * Filter, CSV/PDF-Export, Audit), Muster wie {@see PlanIstReportBuilder}.
 */
class CustomerAnalysisReportBuilder {
    /**
     * @param  list<int>  $excludedCustomerIds  Feature 002: org-weit ausgeblendete
     *         Kunden (customers.exclude_from_reports); Übersteuerung regelt der Aufrufer.
     * @return list<array{
     *   customerId:int,
     *   customerName:string,
     *   entryCount:int,
     *   totalMinutes:int,
     *   billableMinutes:int,
     *   nonBillableMinutes:int,
     *   nonBillableShare:float,
     *   reworkEntryCount:int,
     *   openIssueCount:int,
     *   escalationCount:int,
     *   avgEntryMinutes:int,
     *   trend30d:int
     * }>
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, ?int $projectId, ?int $userId, ?int $entryTypeId = null, array $excludedCustomerIds = []): array {
        // Cache je (Org, Filter) — kundenanalyse.md §6 (Vollscan 2026-08-23, A6).
        $ttl = (int) config('reports.customer_analysis_cache_seconds', 300);
        $organizationId = app()->bound('currentOrganization') ? (int) (app('currentOrganization')->id ?? 0) : 0;
        $cacheKey = sprintf('report.customers.%d.%s', $organizationId, md5(json_encode([
            $from->toIso8601String(), $to->toIso8601String(), $projectId, $userId, $entryTypeId, $excludedCustomerIds,
        ]) ?: ''));

        $compute = fn (): array => $this->aggregate($from, $to, $projectId, $userId, $entryTypeId, $excludedCustomerIds);

        return $ttl > 0 ? Cache::remember($cacheKey, $ttl, $compute) : $compute();
    }

    /**
     * Acht Aggregationen statt ~10 Queries je Kunde (Vollscan 2026-08-23, A6 —
     * Lehre aus dem Buchhaltungs-Lastprofil: Aggregation in SQL, Hydration
     * vermeiden). Die Semantik entspricht der früheren Pro-Kunde-Schleife:
     * Aufträge mit Filtern, Zeiten über die Kundenprojekte (Auftragstyp über
     * die Auftragsverknüpfung), offene Punkte über Kunde/Auftrag/Projekt,
     * Nacharbeit über Mängelprotokolle der gefilterten Aufträge.
     *
     * @param  list<int>  $excludedCustomerIds
     * @return list<array{
     *   customerId:int,
     *   customerName:string,
     *   entryCount:int,
     *   totalMinutes:int,
     *   billableMinutes:int,
     *   nonBillableMinutes:int,
     *   nonBillableShare:float,
     *   reworkEntryCount:int,
     *   openIssueCount:int,
     *   escalationCount:int,
     *   avgEntryMinutes:int,
     *   trend30d:int
     * }>
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, ?int $projectId, ?int $userId, ?int $entryTypeId, array $excludedCustomerIds): array {
        $customers = Customer::query()
            ->when($excludedCustomerIds !== [], fn($q) => $q->whereNotIn('id', $excludedCustomerIds))
            ->orderBy('name')
            ->get(['id', 'name']);
        if ($customers->isEmpty()) {
            return [];
        }
        $customerIds = $customers->pluck('id')->map(static fn($v): int => (int) $v)->all();

        // Gefilterte Aufträge je Kunde (Basis für Zähler, Zeiten-Typfilter, offene Punkte, Nacharbeit).
        $entryFilter = static function ($q, string $table) use ($from, $to, $projectId, $userId, $entryTypeId): void {
            $q->whereBetween($table . '.created_at', [$from, $to])
                ->when($projectId !== null, fn($qq) => $qq->where($table . '.project_id', $projectId))
                ->when($userId !== null, fn($qq) => $qq->where($table . '.user_id', $userId))
                ->when($entryTypeId !== null, fn($qq) => $qq->where($table . '.entry_type_id', $entryTypeId));
        };

        $entryCounts = DiaryEntry::query()
            ->whereIn('diary_entries.customer_id', $customerIds)
            ->tap(fn($q) => $entryFilter($q, 'diary_entries'))
            ->toBase()
            ->selectRaw('diary_entries.customer_id AS customer_id, COUNT(*) AS c')
            ->groupBy('diary_entries.customer_id')
            ->pluck('c', 'customer_id');

        // Zeiten über die Kundenprojekte; mit Auftragstyp-Filter nur Zeiten, die an
        // einem passenden (gefilterten) Auftrag desselben Kunden hängen.
        $timeSums = TimeEntry::query()
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->whereIn('projects.customer_id', $customerIds)
            ->whereBetween('time_entries.date', DateRange::days($from, $to))
            ->when($projectId !== null, fn($q) => $q->where('time_entries.project_id', $projectId))
            ->when($userId !== null, fn($q) => $q->where('time_entries.user_id', $userId))
            ->when($entryTypeId !== null, fn($q) => $q
                ->join('diary_entries AS te_entry', 'te_entry.id', '=', 'time_entries.diary_entry_id')
                ->whereColumn('te_entry.customer_id', 'projects.customer_id')
                ->tap(fn($qq) => $entryFilter($qq, 'te_entry')))
            ->toBase()
            ->selectRaw('projects.customer_id AS customer_id, COALESCE(SUM(time_entries.minutes), 0) AS total, COALESCE(SUM(CASE WHEN time_entries.billable = 1 THEN time_entries.minutes ELSE 0 END), 0) AS billable')
            ->groupBy('projects.customer_id')
            ->get()
            ->keyBy('customer_id');

        // Offene Punkte: drei disjunkte Subjekt-Typen, je Kunde gezählt.
        $openStatuses = OpenIssueStatus::openValues();
        $blocked = OpenIssueStatus::Blocked->value;
        $issueSelect = 'COUNT(*) AS c, SUM(CASE WHEN open_issues.status = ? THEN 1 ELSE 0 END) AS blocked';

        $issuesByCustomer = OpenIssue::query()
            ->whereIn('open_issues.status', $openStatuses)
            ->where('open_issues.subject_type', Customer::class)
            ->whereIn('open_issues.subject_id', $customerIds)
            ->toBase()
            ->selectRaw('open_issues.subject_id AS customer_id, ' . $issueSelect, [$blocked])
            ->groupBy('open_issues.subject_id')
            ->get()->keyBy('customer_id');
        $issuesByEntry = OpenIssue::query()
            ->whereIn('open_issues.status', $openStatuses)
            ->where('open_issues.subject_type', DiaryEntry::class)
            ->join('diary_entries AS oi_entry', 'oi_entry.id', '=', 'open_issues.subject_id')
            ->whereIn('oi_entry.customer_id', $customerIds)
            ->tap(fn($q) => $entryFilter($q, 'oi_entry'))
            ->toBase()
            ->selectRaw('oi_entry.customer_id AS customer_id, ' . $issueSelect, [$blocked])
            ->groupBy('oi_entry.customer_id')
            ->get()->keyBy('customer_id');
        $issuesByProject = OpenIssue::query()
            ->whereIn('open_issues.status', $openStatuses)
            ->where('open_issues.subject_type', Project::class)
            ->join('projects AS oi_project', 'oi_project.id', '=', 'open_issues.subject_id')
            ->whereIn('oi_project.customer_id', $customerIds)
            ->when($projectId !== null, fn($q) => $q->where('oi_project.id', $projectId))
            ->toBase()
            ->selectRaw('oi_project.customer_id AS customer_id, ' . $issueSelect, [$blocked])
            ->groupBy('oi_project.customer_id')
            ->get()->keyBy('customer_id');

        // Nacharbeit: Mängelprotokolle an gefilterten Aufträgen, je Auftrag einmal.
        $rework = Protocol::query()
            ->where('protocols.type', ProtocolType::Defect->value)
            ->where('protocols.subject_type', DiaryEntry::class)
            ->whereBetween('protocols.occurred_at', [$from, $to])
            ->join('diary_entries AS pr_entry', 'pr_entry.id', '=', 'protocols.subject_id')
            ->whereIn('pr_entry.customer_id', $customerIds)
            ->tap(fn($q) => $entryFilter($q, 'pr_entry'))
            ->toBase()
            ->selectRaw('pr_entry.customer_id AS customer_id, COUNT(DISTINCT protocols.subject_id) AS c')
            ->groupBy('pr_entry.customer_id')
            ->pluck('c', 'customer_id');

        // 30-Tage-Trend der Auftragsanzahl (aktueller minus vorheriger Zeitraum).
        $latestFrom = $to->subDays(29)->startOfDay();
        $prevFrom = $latestFrom->subDays(30)->startOfDay();
        $prevTo = $latestFrom->subSecond();
        $trendBase = fn() => DiaryEntry::query()
            ->whereIn('diary_entries.customer_id', $customerIds)
            ->when($projectId !== null, fn($q) => $q->where('diary_entries.project_id', $projectId))
            ->when($userId !== null, fn($q) => $q->where('diary_entries.user_id', $userId))
            ->when($entryTypeId !== null, fn($q) => $q->where('diary_entries.entry_type_id', $entryTypeId))
            ->toBase()
            ->selectRaw('diary_entries.customer_id AS customer_id, COUNT(*) AS c')
            ->groupBy('diary_entries.customer_id');
        $trendLatest = $trendBase()->whereBetween('diary_entries.created_at', [$latestFrom, $to])->pluck('c', 'customer_id');
        $trendPrevious = $trendBase()->whereBetween('diary_entries.created_at', [$prevFrom, $prevTo])->pluck('c', 'customer_id');

        $rows = [];
        foreach ($customers as $customer) {
            $id = (int) $customer->id;
            $entryCount = (int) ($entryCounts[$id] ?? 0);
            $time = $timeSums->get($id);
            $totalMinutes = (int) ($time->total ?? 0);
            $billableMinutes = (int) ($time->billable ?? 0);
            $nonBillableMinutes = max(0, $totalMinutes - $billableMinutes);
            $openIssueCount = (int) (($issuesByCustomer->get($id)->c ?? 0) + ($issuesByEntry->get($id)->c ?? 0) + ($issuesByProject->get($id)->c ?? 0));
            $escalationCount = (int) (($issuesByCustomer->get($id)->blocked ?? 0) + ($issuesByEntry->get($id)->blocked ?? 0) + ($issuesByProject->get($id)->blocked ?? 0));

            $rows[] = [
                'customerId' => $id,
                'customerName' => (string) $customer->name,
                'entryCount' => $entryCount,
                'totalMinutes' => $totalMinutes,
                'billableMinutes' => $billableMinutes,
                'nonBillableMinutes' => $nonBillableMinutes,
                'nonBillableShare' => $totalMinutes > 0 ? round(($nonBillableMinutes / $totalMinutes) * 100, 2) : 0.0,
                'reworkEntryCount' => (int) ($rework[$id] ?? 0),
                'openIssueCount' => $openIssueCount,
                'escalationCount' => $escalationCount,
                'avgEntryMinutes' => $entryCount > 0 ? (int) round($totalMinutes / $entryCount) : 0,
                'trend30d' => (int) ($trendLatest[$id] ?? 0) - (int) ($trendPrevious[$id] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Auftragseingang je Zeit-Bucket im gewählten Zeitraum (kundenübergreifend),
     * in der Header-Granularität — Tagesreihe für den Trend-Linienchart des
     * Kundenreports.
     *
     * @param  'day'|'week'|'month'|'quarter'  $granularity
     * @param  list<int>  $excludedCustomerIds  Feature 002; kundenlose Einträge bleiben sichtbar.
     * @return list<array{label: string, count: int}>
     */
    public function entrySeries(CarbonImmutable $from, CarbonImmutable $to, string $granularity, ?int $projectId, ?int $userId, ?int $entryTypeId = null, array $excludedCustomerIds = []): array {
        /** @var array<string, array{label: string, count: int}> $buckets */
        $buckets = [];
        for ($cursor = $from->startOfDay(); $cursor->lte($to); $cursor = $cursor->addDay()) {
            [$key, $label] = ChartBucket::keyLabel($granularity, $cursor);
            $buckets[$key] ??= ['label' => $label, 'count' => 0];
        }

        DiaryEntry::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to])
            ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId))
            ->when($userId !== null, fn($q) => $q->where('user_id', $userId))
            ->when($entryTypeId !== null, fn($q) => $q->where('entry_type_id', $entryTypeId))
            // NOT IN würde NULL-Kunden mit verwerfen — kundenlose Einträge bleiben sichtbar.
            ->when($excludedCustomerIds !== [], fn($q) => $q->where(
                fn($w) => $w->whereNull('customer_id')->orWhereNotIn('customer_id', $excludedCustomerIds),
            ))
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$buckets, $granularity): void {
                $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $createdAt))[0];
                if (isset($buckets[$key])) {
                    $buckets[$key]['count']++;
                }
            });

        return array_values($buckets);
    }
}
