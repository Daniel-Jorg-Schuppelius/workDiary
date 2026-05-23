<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerAnalysisReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DiaryEntry;
use App\Models\OpenIssue;
use App\Models\Project;
use App\Models\Protocol;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerAnalysisReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request): View {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $minMinutes = max(0, (int) $request->integer('min_minutes', 0));
        $projectId = $request->filled('project_id') ? (int) $request->integer('project_id') : null;
        $userId = $request->filled('user_id') ? (int) $request->integer('user_id') : null;

        $rows = collect($this->buildRows($from, $to, $projectId, $userId))
            ->filter(static fn (array $row): bool => $row['totalMinutes'] >= $minMinutes)
            ->values();

        $topByMinutes = $rows->sortByDesc('totalMinutes')->take(5)->values();
        $topByRework = $rows->sortByDesc('reworkEntryCount')->take(5)->values();
        $topByNonBillable = $rows->sortByDesc('nonBillableMinutes')->take(5)->values();

        return view('reports.customers', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'label' => $range['label'],
            'minMinutes' => $minMinutes,
            'projectId' => $projectId,
            'userId' => $userId,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'topByMinutes' => $topByMinutes,
            'topByRework' => $topByRework,
            'topByNonBillable' => $topByNonBillable,
        ]);
    }

    /**
     * @return list<array{
     *   customerId:int,
     *   customerName:string,
     *   entryCount:int<0, max>,
     *   totalMinutes:int,
     *   billableMinutes:int,
     *   nonBillableMinutes:int<0, max>,
     *   nonBillableShare:float,
     *   reworkEntryCount:int<0, max>,
     *   openIssueCount:int,
     *   escalationCount:int,
     *   avgEntryMinutes:int,
     *   trend30d:int
     * }>
     */
    private function buildRows(CarbonImmutable $from, CarbonImmutable $to, ?int $projectId, ?int $userId): array {
        return array_values(Customer::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Customer $customer) use ($from, $to, $projectId, $userId): array {
                $projectIds = Project::query()
                    ->where('customer_id', $customer->id)
                    ->when($projectId !== null, fn ($q) => $q->where('id', $projectId))
                    ->pluck('id')
                    ->map(static fn ($v): int => (int) $v)
                    ->all();

                $entryQuery = DiaryEntry::query()
                    ->where('customer_id', $customer->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                    ->when($userId !== null, fn ($q) => $q->where('user_id', $userId));

                $entryIds = $entryQuery->clone()->pluck('id')->map(static fn ($v): int => (int) $v)->all();
                $entryCount = count($entryIds);

                $timeQuery = TimeEntry::query()
                    ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                    ->when($projectIds !== [], fn ($q) => $q->whereIn('project_id', $projectIds), fn ($q) => $q->whereRaw('1=0'))
                    ->when($userId !== null, fn ($q) => $q->where('user_id', $userId));

                $totalMinutes = (int) $timeQuery->clone()->sum('minutes');
                $billableMinutes = (int) $timeQuery->clone()->where('billable', true)->sum('minutes');
                $nonBillableMinutes = max(0, $totalMinutes - $billableMinutes);
                $nonBillableShare = $totalMinutes > 0
                    ? round(($nonBillableMinutes / $totalMinutes) * 100, 2)
                    : 0.0;

                $openStatuses = [
                    OpenIssueStatus::Open->value,
                    OpenIssueStatus::InProgress->value,
                    OpenIssueStatus::Blocked->value,
                    OpenIssueStatus::Reopened->value,
                ];

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
                    ->when($entryIds !== [], fn ($q) => $q->whereIn('subject_id', $entryIds), fn ($q) => $q->whereRaw('1=0'))
                    ->distinct('subject_id')
                    ->count('subject_id');

                $avgEntryMinutes = $entryCount > 0
                    ? (int) round($totalMinutes / $entryCount)
                    : 0;

                $trend30d = $this->trend30d($customer->id, $projectId, $userId, $to);

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

    private function trend30d(int $customerId, ?int $projectId, ?int $userId, CarbonImmutable $to): int {
        $latestFrom = $to->subDays(29)->startOfDay();
        $prevFrom = $latestFrom->subDays(30)->startOfDay();
        $prevTo = $latestFrom->subSecond();

        $base = DiaryEntry::query()
            ->where('customer_id', $customerId)
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId));

        $latest = (int) $base->clone()->whereBetween('created_at', [$latestFrom, $to])->count();
        $previous = (int) $base->clone()->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        return $latest - $previous;
    }
}
