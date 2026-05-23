<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeAnalysisReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Diary\Status as DiaryStatus;
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DiaryEntry;
use App\Models\EntryType;
use App\Models\OpenIssue;
use App\Models\Protocol;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EntryTypeAnalysisReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request): View {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $customerId = $request->filled('customer_id') ? (int) $request->integer('customer_id') : null;
        $userId = $request->filled('user_id') ? (int) $request->integer('user_id') : null;
        $entryTypeFilter = $request->filled('entry_type_id') ? (int) $request->integer('entry_type_id') : null;
        $statusFilter = $request->filled('status') ? (int) $request->integer('status') : null;

        $rows = $this->buildRows($from, $to, $customerId, $userId, $entryTypeFilter, $statusFilter);

        return view('reports.entry-types', [
            'rows' => $rows,
            'label' => $range['label'],
            'from' => $from,
            'to' => $to,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'entryTypes' => EntryType::query()->ordered()->get(['id', 'label']),
            'customerId' => $customerId,
            'userId' => $userId,
            'entryTypeFilter' => $entryTypeFilter,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * @return list<array{
     *   entryTypeId:int,
     *   entryTypeName:string,
     *   entryCount:int,
     *   avgPlannedMinutes:float,
     *   avgActualMinutes:float,
     *   planActualRatio:float|null,
     *   overrunCount:int,
     *   overrunShare:float,
     *   reworkCount:int,
     *   reworkShare:float,
     *   escalationCount:int,
     *   escalationShare:float,
     *   firstTimeRightShare:float,
     *   medianActualMinutes:float,
     *   p90ActualMinutes:float
     * }>
     */
    private function buildRows(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $customerId,
        ?int $userId,
        ?int $entryTypeFilter,
        ?int $statusFilter,
    ): array {
        $entries = DiaryEntry::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($customerId !== null, fn ($q) => $q->where('customer_id', $customerId))
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->when($entryTypeFilter !== null, fn ($q) => $q->where('entry_type_id', $entryTypeFilter))
            ->when($statusFilter !== null, fn ($q) => $q->where('status', $statusFilter))
            ->get(['id', 'entry_type_id', 'planned_minutes', 'service_minutes']);

        if ($entries->isEmpty()) {
            return [];
        }

        /** @var list<int> $entryIds */
        $entryIds = $entries->pluck('id')->map(static fn ($v): int => (int) $v)->values()->all();

        $actualByEntry = TimeEntry::query()
            ->whereIn('diary_entry_id', $entryIds)
            ->selectRaw('diary_entry_id, COALESCE(SUM(minutes), 0) as total_minutes')
            ->groupBy('diary_entry_id')
            ->pluck('total_minutes', 'diary_entry_id')
            ->map(static fn ($v): int => (int) $v)
            ->all();

        /** @var list<int> $reworkEntryIds */
        $reworkEntryIds = Protocol::query()
            ->where('subject_type', DiaryEntry::class)
            ->where('type', ProtocolType::Defect->value)
            ->whereIn('subject_id', $entryIds)
            ->whereBetween('occurred_at', [$from, $to])
            ->distinct('subject_id')
            ->pluck('subject_id')
            ->map(static fn ($v): int => (int) $v)
            ->values()
            ->all();

        /** @var list<int> $escalatedEntryIds */
        $escalatedEntryIds = OpenIssue::query()
            ->where('subject_type', DiaryEntry::class)
            ->whereIn('subject_id', $entryIds)
            ->where('status', OpenIssueStatus::Blocked->value)
            ->distinct('subject_id')
            ->pluck('subject_id')
            ->map(static fn ($v): int => (int) $v)
            ->values()
            ->all();

        $entryTypeLabels = EntryType::query()
            ->whereIn('id', $entries->pluck('entry_type_id')->filter()->all())
            ->pluck('label', 'id')
            ->all();

        /** @var array<int, array{entryTypeId:int,entryTypeName:string,entryCount:int,plannedSum:int,plannedKnownCount:int,actualValues:list<int>,overrunCount:int,reworkCount:int,escalationCount:int}> $bucket */
        $bucket = [];

        foreach ($entries as $entry) {
            $typeId = (int) ($entry->entry_type_id ?? 0);
            if (! isset($bucket[$typeId])) {
                $bucket[$typeId] = [
                    'entryTypeId' => $typeId,
                    'entryTypeName' => $typeId > 0 ? (string) ($entryTypeLabels[$typeId] ?? ('#'.$typeId)) : (string) __('Ohne Auftragstyp'),
                    'entryCount' => 0,
                    'plannedSum' => 0,
                    'plannedKnownCount' => 0,
                    'actualValues' => [],
                    'overrunCount' => 0,
                    'reworkCount' => 0,
                    'escalationCount' => 0,
                ];
            }

            $bucket[$typeId]['entryCount']++;

            $planned = $entry->planned_minutes;
            if ($planned !== null) {
                $bucket[$typeId]['plannedSum'] += (int) $planned;
                $bucket[$typeId]['plannedKnownCount']++;
            }

            $actual = (int) ($actualByEntry[$entry->id] ?? 0);
            $bucket[$typeId]['actualValues'][] = $actual;

            if ($planned !== null && $planned > 0 && $actual > (int) round($planned * 1.2)) {
                $bucket[$typeId]['overrunCount']++;
            }

            if (in_array((int) $entry->id, $reworkEntryIds, true)) {
                $bucket[$typeId]['reworkCount']++;
            }

            if (in_array((int) $entry->id, $escalatedEntryIds, true)) {
                $bucket[$typeId]['escalationCount']++;
            }
        }

        $rows = [];
        foreach ($bucket as $row) {
            $entryCount = max(1, $row['entryCount']);
            $actualValues = $row['actualValues'];
            sort($actualValues);

            $actualTotal = array_sum($actualValues);
            $avgActual = $row['entryCount'] > 0 ? round($actualTotal / $row['entryCount'], 2) : 0.0;
            $avgPlanned = $row['plannedKnownCount'] > 0 ? round($row['plannedSum'] / $row['plannedKnownCount'], 2) : 0.0;
            $ratio = $avgPlanned > 0 ? round($avgActual / $avgPlanned, 3) : null;

            $overrunShare = round(($row['overrunCount'] / $entryCount) * 100, 2);
            $reworkShare = round(($row['reworkCount'] / $entryCount) * 100, 2);
            $escalationShare = round(($row['escalationCount'] / $entryCount) * 100, 2);
            $firstTimeRightShare = max(0.0, round(100 - $reworkShare - $escalationShare, 2));

            $rows[] = [
                'entryTypeId' => $row['entryTypeId'],
                'entryTypeName' => $row['entryTypeName'],
                'entryCount' => $row['entryCount'],
                'avgPlannedMinutes' => $avgPlanned,
                'avgActualMinutes' => $avgActual,
                'planActualRatio' => $ratio,
                'overrunCount' => $row['overrunCount'],
                'overrunShare' => $overrunShare,
                'reworkCount' => $row['reworkCount'],
                'reworkShare' => $reworkShare,
                'escalationCount' => $row['escalationCount'],
                'escalationShare' => $escalationShare,
                'firstTimeRightShare' => $firstTimeRightShare,
                'medianActualMinutes' => $this->percentile($actualValues, 50),
                'p90ActualMinutes' => $this->percentile($actualValues, 90),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strnatcasecmp($a['entryTypeName'], $b['entryTypeName']));

        return $rows;
    }

    /**
     * @param  list<int>  $values
     */
    private function percentile(array $values, int $percent): float {
        if ($values === []) {
            return 0.0;
        }

        $index = ($percent / 100) * (count($values) - 1);
        $low = (int) floor($index);
        $high = (int) ceil($index);

        if ($low === $high) {
            return (float) $values[$low];
        }

        $weight = $index - $low;

        return round(($values[$low] * (1 - $weight)) + ($values[$high] * $weight), 2);
    }

    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array {
        $options = [];
        foreach (DiaryStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
