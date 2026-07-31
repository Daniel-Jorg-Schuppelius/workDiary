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
use Carbon\CarbonImmutable;

/**
 * Kundenanalyse-Kennzahlen (MVP-039): Aufwand, abrechenbare und nicht
 * abrechenbare Zeit, Nacharbeit, offene Punkte und 30-Tage-Trend je Kunde.
 *
 * Die reine Datenaufbereitung liegt bewusst getrennt vom Controller (HTTP-
 * Filter, CSV/PDF-Export, Audit), Muster wie {@see PlanIstReportBuilder}.
 */
class CustomerAnalysisReportBuilder {
    /**
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
    public function build(CarbonImmutable $from, CarbonImmutable $to, ?int $projectId, ?int $userId, ?int $entryTypeId = null): array {
        return array_values(Customer::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Customer $customer) use ($from, $to, $projectId, $userId, $entryTypeId): array {
                $projectIds = Project::query()
                    ->where('customer_id', $customer->id)
                    ->when($projectId !== null, fn($q) => $q->where('id', $projectId))
                    ->pluck('id')
                    ->map(static fn($v): int => (int) $v)
                    ->all();

                $entryQuery = DiaryEntry::query()
                    ->where('customer_id', $customer->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId))
                    ->when($userId !== null, fn($q) => $q->where('user_id', $userId))
                    ->when($entryTypeId !== null, fn($q) => $q->where('entry_type_id', $entryTypeId));

                $entryIds = $entryQuery->clone()->pluck('id')->map(static fn($v): int => (int) $v)->all();
                $entryCount = count($entryIds);

                $timeQuery = TimeEntry::query()
                    ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                    ->when($projectIds !== [], fn($q) => $q->whereIn('project_id', $projectIds), fn($q) => $q->whereRaw('1=0'))
                    ->when($userId !== null, fn($q) => $q->where('user_id', $userId))
                    // Auftragstyp lebt am Auftrag (diary_entries) — Zeiten folgen
                    // über ihre Auftragsverknüpfung; ohne Auftrag keine Typzuordnung.
                    ->when($entryTypeId !== null, fn($q) => $entryIds !== [] ? $q->whereIn('diary_entry_id', $entryIds) : $q->whereRaw('1=0'));

                $totalMinutes = (int) $timeQuery->clone()->sum('minutes');
                $billableMinutes = (int) $timeQuery->clone()->where('billable', true)->sum('minutes');
                $nonBillableMinutes = max(0, $totalMinutes - $billableMinutes);
                $nonBillableShare = $totalMinutes > 0
                    ? round(($nonBillableMinutes / $totalMinutes) * 100, 2)
                    : 0.0;

                $openStatuses = OpenIssueStatus::openValues();

                $openIssuesQuery = OpenIssue::query()
                    ->whereIn('status', $openStatuses)
                    ->where(function ($q) use ($customer, $entryIds, $projectIds): void {
                        $q->where(function ($sub) use ($customer): void {
                            $sub->where('subject_type', Customer::class)
                                ->where('subject_id', $customer->id);
                        });

                        if ($entryIds !== []) {
                            $q->orWhere(function ($sub) use ($entryIds): void {
                                $sub->where('subject_type', DiaryEntry::class)
                                    ->whereIn('subject_id', $entryIds);
                            });
                        }

                        if ($projectIds !== []) {
                            $q->orWhere(function ($sub) use ($projectIds): void {
                                $sub->where('subject_type', Project::class)
                                    ->whereIn('subject_id', $projectIds);
                            });
                        }
                    });

                $openIssueCount = (int) $openIssuesQuery->clone()->count();
                $escalationCount = (int) $openIssuesQuery->clone()->where('status', OpenIssueStatus::Blocked->value)->count();

                $reworkEntryCount = (int) Protocol::query()
                    ->where('type', ProtocolType::Defect->value)
                    ->where('subject_type', DiaryEntry::class)
                    ->whereBetween('occurred_at', [$from, $to])
                    ->when($entryIds !== [], fn($q) => $q->whereIn('subject_id', $entryIds), fn($q) => $q->whereRaw('1=0'))
                    ->distinct('subject_id')
                    ->count('subject_id');

                $avgEntryMinutes = $entryCount > 0
                    ? (int) round($totalMinutes / $entryCount)
                    : 0;

                $trend30d = $this->trend30d($customer->id, $projectId, $userId, $to, $entryTypeId);

                return [
                    'customerId' => $customer->id,
                    'customerName' => $customer->name,
                    'entryCount' => $entryCount,
                    'totalMinutes' => $totalMinutes,
                    'billableMinutes' => $billableMinutes,
                    'nonBillableMinutes' => $nonBillableMinutes,
                    'nonBillableShare' => $nonBillableShare,
                    'reworkEntryCount' => $reworkEntryCount,
                    'openIssueCount' => $openIssueCount,
                    'escalationCount' => $escalationCount,
                    'avgEntryMinutes' => $avgEntryMinutes,
                    'trend30d' => $trend30d,
                ];
            })
            ->values()
            ->all());
    }

    /** 30-Tage-Trend der Auftragsanzahl (aktueller minus vorheriger Zeitraum). */
    private function trend30d(int $customerId, ?int $projectId, ?int $userId, CarbonImmutable $to, ?int $entryTypeId = null): int {
        $latestFrom = $to->subDays(29)->startOfDay();
        $prevFrom = $latestFrom->subDays(30)->startOfDay();
        $prevTo = $latestFrom->subSecond();

        $base = DiaryEntry::query()
            ->where('customer_id', $customerId)
            ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId))
            ->when($userId !== null, fn($q) => $q->where('user_id', $userId))
            ->when($entryTypeId !== null, fn($q) => $q->where('entry_type_id', $entryTypeId));

        $latest = (int) $base->clone()->whereBetween('created_at', [$latestFrom, $to])->count();
        $previous = (int) $base->clone()->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        return $latest - $previous;
    }

    /**
     * Auftragseingang pro Tag der letzten 30 Tage (kundenübergreifend) —
     * dasselbe Zeitfenster wie {@see trend30d()}, als Tagesreihe für den
     * Trend-Linienchart des Kundenreports.
     *
     * @return list<array{date: CarbonImmutable, count: int}>
     */
    public function dailyEntrySeries30d(CarbonImmutable $to, ?int $projectId, ?int $userId, ?int $entryTypeId = null): array {
        $from = $to->subDays(29)->startOfDay();

        /** @var array<string, int> $byDay */
        $byDay = [];
        DiaryEntry::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId))
            ->when($userId !== null, fn($q) => $q->where('user_id', $userId))
            ->when($entryTypeId !== null, fn($q) => $q->where('entry_type_id', $entryTypeId))
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$byDay): void {
                $key = CarbonImmutable::parse((string) $createdAt)->toDateString();
                $byDay[$key] = ($byDay[$key] ?? 0) + 1;
            });

        $series = [];
        for ($cursor = $from; $cursor->lte($to); $cursor = $cursor->addDay()) {
            $series[] = [
                'date' => $cursor,
                'count' => $byDay[$cursor->toDateString()] ?? 0,
            ];
        }

        return $series;
    }
}
