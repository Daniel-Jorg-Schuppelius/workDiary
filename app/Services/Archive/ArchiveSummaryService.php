<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArchiveSummaryService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Archive;

use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use App\Models\Vacation;
use App\Support\SortableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Aggregiert Daten für die Archiv-Übersicht
 * (Tagebuch / Bereitschaft / Notdienst / Urlaub).
 */
class ArchiveSummaryService
{
    private const ALLOWED_TABS = ['diary', 'bereitschaft', 'notdienst', 'urlaub'];

    private const ALLOWED_STATUS = ['all', '-1', '1', '2', '3'];

    /**
     * @return array<string,mixed>
     */
    public function buildIndexData(Request $request, User $currentUser, string $rangeFrom, string $rangeTo): array
    {
        $isAdmin = $currentUser->isAdmin();
        $tab = $this->resolveTab((string) $request->query('tab', 'diary'));
        $statusFilter = $this->resolveStatus((string) $request->query('status', 'all'));

        $diaryQuery = $this->buildDiaryQuery();
        $shiftQuery = $this->buildShiftQuery();
        $assignmentQuery = $this->buildAssignmentQuery();
        $vacationQuery = $this->buildVacationQuery();

        $queries = [$diaryQuery, $shiftQuery, $assignmentQuery, $vacationQuery];

        if (! $isAdmin) {
            foreach ($queries as $q) {
                $q->where('user_id', $currentUser->id);
            }
        } elseif ($request->filled('user_id')) {
            $uid = (int) $request->user_id;
            foreach ($queries as $q) {
                $q->where('user_id', $uid);
            }
        }

        foreach ([$diaryQuery, $shiftQuery, $assignmentQuery] as $q) {
            $q->whereDate('end_at', '>=', $rangeFrom);
            $q->whereDate('end_at', '<=', $rangeTo);
        }
        $vacationQuery->whereDate('end_date', '>=', $rangeFrom);
        $vacationQuery->whereDate('end_date', '<=', $rangeTo);

        if ($request->filled('vtype')) {
            $vacationQuery->where('type', $request->vtype);
        }
        if ($request->filled('vstatus')) {
            $vacationQuery->where('status', $request->vstatus);
        }
        if ($tab === 'diary' && $statusFilter !== 'all') {
            $diaryQuery->where('status', (int) $statusFilter);
        }

        $counts = [
            'diary' => (clone $diaryQuery)->count(),
            'bereitschaft' => (clone $shiftQuery)->count(),
            'notdienst' => (clone $assignmentQuery)->count(),
            'urlaub' => (clone $vacationQuery)->count(),
        ];

        $tabKpis = $this->buildTabKpis($tab, $counts, $diaryQuery, $shiftQuery, $assignmentQuery, $vacationQuery);

        // Sortierung pro aktivem Tab.
        $sort = '';
        $dir = 'desc';
        if ($tab === 'diary') {
            [$sort, $dir] = SortableQuery::apply($diaryQuery, $request, [
                'mitarbeiter' => 'user_id',
                'status' => 'status',
                'start' => 'start_at',
                'end' => 'end_at',
                'archived' => 'archived_at',
            ], 'archived', 'desc');
        } elseif ($tab === 'bereitschaft') {
            [$sort, $dir] = SortableQuery::apply($shiftQuery, $request, [
                'mitarbeiter' => 'user_id',
                'start' => 'start_at',
                'end' => 'end_at',
            ], 'end', 'desc');
        } elseif ($tab === 'notdienst') {
            [$sort, $dir] = SortableQuery::apply($assignmentQuery, $request, [
                'mitarbeiter' => 'user_id',
                'start' => 'start_at',
                'end' => 'end_at',
            ], 'end', 'desc');
        } elseif ($tab === 'urlaub') {
            [$sort, $dir] = SortableQuery::apply($vacationQuery, $request, [
                'mitarbeiter' => 'user_id',
                'typ' => 'type',
                'status' => 'status',
                'start' => 'start_date',
                'end' => 'end_date',
            ], 'end', 'desc');
        }

        return [
            'isAdmin' => $isAdmin,
            'users' => $isAdmin ? User::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'tab' => $tab,
            'statusFilter' => $statusFilter,
            'filters' => array_merge(
                $request->only('status', 'user_id', 'vtype', 'vstatus'),
                ['from' => $rangeFrom, 'to' => $rangeTo],
            ),
            'counts' => $counts,
            'tabKpis' => $tabKpis,
            'diaryEntries' => $diaryQuery->paginate(25, ['*'], 'dpage')->withQueryString(),
            'shiftEntries' => $shiftQuery->paginate(25, ['*'], 'spage')->withQueryString(),
            'assignmentEntries' => $assignmentQuery->paginate(25, ['*'], 'apage')->withQueryString(),
            'vacationEntries' => $vacationQuery->paginate(25, ['*'], 'vpage')->withQueryString(),
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    private function resolveTab(string $tab): string
    {
        return in_array($tab, self::ALLOWED_TABS, true) ? $tab : 'diary';
    }

    private function resolveStatus(string $status): string
    {
        return in_array($status, self::ALLOWED_STATUS, true) ? $status : 'all';
    }

    /**
     * @return Builder<DiaryEntry>
     */
    private function buildDiaryQuery(): Builder
    {
        /** @var Builder<DiaryEntry> $q */
        $q = DiaryEntry::query()
            ->select(['id', 'user_id', 'content', 'status', 'start_at', 'end_at', 'archived_at'])
            ->with('user:id,name')
            ->where('is_archived', true)
            ->orderByDesc('archived_at');

        return $q;
    }

    /**
     * @return Builder<OnCallShift>
     */
    private function buildShiftQuery(): Builder
    {
        /** @var Builder<OnCallShift> $q */
        $q = OnCallShift::query()
            ->select(['id', 'user_id', 'start_at', 'end_at', 'note'])
            ->with('user:id,name')
            ->where('is_archived', true)
            ->orderByDesc('end_at');

        return $q;
    }

    /**
     * @return Builder<EmergencyAssignment>
     */
    private function buildAssignmentQuery(): Builder
    {
        /** @var Builder<EmergencyAssignment> $q */
        $q = EmergencyAssignment::query()
            ->select(['id', 'user_id', 'on_call_shift_id', 'start_at', 'end_at', 'reason'])
            ->with(['user:id,name', 'shift:id,start_at,end_at'])
            ->where('is_archived', true)
            ->orderByDesc('end_at');

        return $q;
    }

    /**
     * @return Builder<Vacation>
     */
    private function buildVacationQuery(): Builder
    {
        /** @var Builder<Vacation> $q */
        $q = Vacation::query()
            ->with('user:id,name')
            ->where(function ($q) {
                $q->whereIn('status', [Vacation::STATUS_REJECTED, Vacation::STATUS_CANCELLED])
                    ->orWhere(function ($q2) {
                        $q2->where('status', Vacation::STATUS_APPROVED)
                            ->where('end_date', '<', now()->toDateString());
                    });
            })
            ->orderByDesc('end_date');

        return $q;
    }

    /**
     * @param  array<string,int>  $counts
     * @param  Builder<DiaryEntry>  $diaryQuery
     * @param  Builder<OnCallShift>  $shiftQuery
     * @param  Builder<EmergencyAssignment>  $assignmentQuery
     * @param  Builder<Vacation>  $vacationQuery
     * @return array<string,int|float>
     */
    private function buildTabKpis(
        string $tab,
        array $counts,
        Builder $diaryQuery,
        Builder $shiftQuery,
        Builder $assignmentQuery,
        Builder $vacationQuery,
    ): array {
        return match ($tab) {
            'urlaub' => [
                'total' => $counts['urlaub'],
                'rejected' => (clone $vacationQuery)->where('status', Vacation::STATUS_REJECTED)->count(),
                'cancelled' => (clone $vacationQuery)->where('status', Vacation::STATUS_CANCELLED)->count(),
                'expired' => (clone $vacationQuery)->where('status', Vacation::STATUS_APPROVED)
                    ->where('end_date', '<', now()->toDateString())->count(),
            ],
            'diary' => [
                'total' => $counts['diary'],
                'erledigt' => (clone $diaryQuery)->where('status', -1)->count(),
                'offen' => (clone $diaryQuery)->where('status', 2)->count(),
                'alert' => (clone $diaryQuery)->where('status', 3)->count(),
            ],
            'bereitschaft' => $this->durationKpis($shiftQuery, $counts['bereitschaft']),
            default => $this->durationKpis($assignmentQuery, $counts['notdienst']),
        };
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return array<string,int|float>
     */
    private function durationKpis(Builder $query, int $total): array
    {
        $base = clone $query;
        $durations = (clone $base)->get(['start_at', 'end_at'])
            ->map(function ($row) {
                /** @var OnCallShift|EmergencyAssignment $row */
                return (int) $row->start_at->startOfDay()->diffInDays($row->end_at->startOfDay()) + 1;
            });

        return [
            'total' => $total,
            'longest' => (int) ($durations->max() ?? 0),
            'avg' => $durations->count() > 0 ? round((float) $durations->avg(), 1) : 0,
            'users' => (clone $base)->distinct()->count('user_id'),
        ];
    }
}
