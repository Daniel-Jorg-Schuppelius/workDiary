<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DutyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vacation;
use App\Services\HolidayService;
use App\Services\UI\DateRangeContext;
use App\Support\SortableQuery;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DutyController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request, HolidayService $holidayService): View|RedirectResponse {
        // Backward-Compat: ?from=&to= einmalig in den globalen Context.
        if ($request->filled('from') || $request->filled('to')) {
            app(DateRangeContext::class)->set(
                DateRangeContext::PRESET_CUSTOM,
                (string) $request->query('from', ''),
                (string) $request->query('to', ''),
            );

            return redirect()->route('duties.index', $request->except(['from', 'to']));
        }

        $tab = (string) $request->query('tab', 'diary');
        if (! in_array($tab, ['diary', 'bereitschaft', 'notdienst', 'urlaub'], true)) {
            $tab = 'diary';
        }

        /** @var User $authUser */
        $authUser = Auth::user();
        $isAdmin = $authUser->isAdmin();

        $range = $this->globalDateRange();
        $rangeFrom = $range['from']->toDateString();
        $rangeTo = $range['to']->toDateString();

        $diaryQuery = $this->buildDiaryQuery($request, $rangeFrom, $rangeTo);
        $shiftQuery = $this->buildShiftQuery($rangeFrom, $rangeTo);
        $vacationQuery = $this->buildVacationQuery($request, $authUser, $isAdmin, $rangeFrom, $rangeTo);
        $assignmentQuery = $this->buildAssignmentQuery($rangeFrom, $rangeTo);

        $tabCounts = [
            'diary' => (clone $diaryQuery)->count(),
            'bereitschaft' => (clone $shiftQuery)->count(),
            'notdienst' => (clone $assignmentQuery)->count(),
            'urlaub' => (clone $vacationQuery)->count(),
        ];

        $shiftKpis = $this->computeDurationKpis($shiftQuery, $tabCounts['bereitschaft']);
        $assignmentKpis = $this->computeDurationKpis($assignmentQuery, $tabCounts['notdienst']);
        $vacationKpis = $this->computeVacationKpis($vacationQuery);

        $allTags = Tag::orderBy('name')->get(['id', 'name', 'color']);
        $users = $isAdmin ? User::query()->orderBy('name')->get(['id', 'name']) : collect();
        $filters = $request->only('status', 'mine', 'q', 'tag', 'vtype', 'vstatus', 'user_id');
        $filters['from'] = $rangeFrom;
        $filters['to'] = $rangeTo;

        [$sort, $dir] = $this->applyTabSort($tab, $request, $diaryQuery, $shiftQuery, $assignmentQuery, $vacationQuery);

        return view('duties.index', [
            'tab' => $tab,
            'filters' => $filters,
            'tabCounts' => $tabCounts,
            'diaryCounts' => $this->computeDiaryCounts(),
            'shiftKpis' => $shiftKpis,
            'assignmentKpis' => $assignmentKpis,
            'allTags' => $allTags,
            'entries' => $diaryQuery->paginate(20, ['*'], 'dpage')->withQueryString(),
            'shifts' => $shiftQuery->paginate(15, ['*'], 'spage')->withQueryString(),
            'assignments' => $assignmentQuery->paginate(15, ['*'], 'apage')->withQueryString(),
            'vacations' => $vacationQuery->paginate(15, ['*'], 'vpage')->withQueryString(),
            'vacationKpis' => $vacationKpis,
            'isAdmin' => $isAdmin,
            'users' => $users,
            'holidayService' => $holidayService,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /**
     * @return EloquentBuilder<DiaryEntry>
     */
    private function buildDiaryQuery(Request $request, string $rangeFrom, string $rangeTo): EloquentBuilder {
        /** @var EloquentBuilder<DiaryEntry> $diaryQuery */
        $diaryQuery = DiaryEntry::query()
            ->select(['id', 'user_id', 'content', 'status', 'is_archived', 'start_at', 'end_at', 'created_at'])
            ->with(['user:id,name', 'tags:id,name,color,slug'])
            ->where('is_archived', false)
            ->orderByDesc('start_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $diaryQuery->where('status', (int) $request->status);
        }
        $diaryQuery->whereDate('start_at', '>=', $rangeFrom);
        $diaryQuery->whereDate('start_at', '<=', $rangeTo);
        if ($request->boolean('mine')) {
            $diaryQuery->where('user_id', Auth::id());
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $diaryQuery->where(fn($w) => $w->where('content', 'like', $like)->orWhere('response', 'like', $like));
        }
        $tagId = $request->integer('tag');
        if ($tagId > 0) {
            $diaryQuery->whereHas('tags', fn($tq) => $tq->where('tags.id', $tagId));
        }

        return $diaryQuery;
    }

    /**
     * @return EloquentBuilder<OnCallShift>
     */
    private function buildShiftQuery(string $rangeFrom, string $rangeTo): EloquentBuilder {
        /** @var EloquentBuilder<OnCallShift> $q */
        $q = OnCallShift::query()
            ->with('user:id,name')
            ->where('is_archived', false)
            ->orderByDesc('start_at')
            ->whereDate('start_at', '>=', $rangeFrom)
            ->whereDate('start_at', '<=', $rangeTo);

        return $q;
    }

    /**
     * @return EloquentBuilder<Vacation>
     */
    private function buildVacationQuery(Request $request, User $authUser, bool $isAdmin, string $rangeFrom, string $rangeTo): EloquentBuilder {
        /** @var EloquentBuilder<Vacation> $vacationQuery */
        $vacationQuery = Vacation::query()
            ->with('user:id,name')
            ->orderByDesc('start_date');

        if (! $isAdmin) {
            $vacationQuery->where('user_id', $authUser->id);
        } elseif ($request->filled('user_id')) {
            $vacationQuery->where('user_id', (int) $request->user_id);
        }
        if ($request->filled('vtype')) {
            $vacationQuery->where('type', $request->vtype);
        }
        if ($request->filled('vstatus')) {
            $vacationQuery->where('status', $request->vstatus);
        }
        $vacationQuery->where('end_date', '>=', $rangeFrom);
        $vacationQuery->where('start_date', '<=', $rangeTo);

        return $vacationQuery;
    }

    /**
     * @return EloquentBuilder<EmergencyAssignment>
     */
    private function buildAssignmentQuery(string $rangeFrom, string $rangeTo): EloquentBuilder {
        /** @var EloquentBuilder<EmergencyAssignment> $q */
        $q = EmergencyAssignment::query()
            ->with(['user:id,name', 'shift:id,start_at,end_at,user_id'])
            ->where('is_archived', false)
            ->orderByDesc('start_at')
            ->whereDate('start_at', '>=', $rangeFrom)
            ->whereDate('start_at', '<=', $rangeTo);

        return $q;
    }

    /**
     * @return array{all:int, open:int, alert:int, done:int}
     */
    private function computeDiaryCounts(): array {
        $row = DiaryEntry::query()
            ->where('is_archived', false)
            ->toBase()
            ->selectRaw(
                'COUNT(*) as cnt_all,' .
                    'COUNT(CASE WHEN status = 2 THEN 1 END) as cnt_open,' .
                    'COUNT(CASE WHEN status = 3 THEN 1 END) as cnt_alert,' .
                    'COUNT(CASE WHEN status = -1 THEN 1 END) as cnt_done'
            )
            ->first();

        return [
            'all' => (int) ($row->cnt_all ?? 0),
            'open' => (int) ($row->cnt_open ?? 0),
            'alert' => (int) ($row->cnt_alert ?? 0),
            'done' => (int) ($row->cnt_done ?? 0),
        ];
    }

    /**
     * Berechnet Dauer-KPIs (total/longest/avg/users) für Shift- oder Assignment-Queries.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @return array{total:int, longest:int, avg:float|int, users:int}
     */
    private function computeDurationKpis(EloquentBuilder $query, int $total): array {
        $durations = (clone $query)->get(['start_at', 'end_at'])
            /** @phpstan-ignore property.notFound, property.notFound */
            ->map(fn($r) => $r->start_at && $r->end_at
                ? (int) $r->start_at->startOfDay()->diffInDays($r->end_at->startOfDay()) + 1
                : 0);

        return [
            'total' => $total,
            'longest' => (int) ($durations->max() ?? 0),
            'avg' => $durations->count() > 0 ? round($durations->avg(), 1) : 0,
            'users' => (clone $query)->distinct()->count('user_id'),
        ];
    }

    /**
     * @param  EloquentBuilder<Vacation>  $query
     * @return array{total:int, pending:int, approved:int, rejected:int}
     */
    private function computeVacationKpis(EloquentBuilder $query): array {
        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', Vacation::STATUS_PENDING)->count(),
            'approved' => (clone $query)->where('status', Vacation::STATUS_APPROVED)
                ->where('end_date', '>=', now()->startOfYear())->count(),
            'rejected' => (clone $query)->where('status', Vacation::STATUS_REJECTED)->count(),
        ];
    }

    /**
     * @param  EloquentBuilder<DiaryEntry>  $diaryQuery
     * @param  EloquentBuilder<OnCallShift>  $shiftQuery
     * @param  EloquentBuilder<EmergencyAssignment>  $assignmentQuery
     * @param  EloquentBuilder<Vacation>  $vacationQuery
     * @return array{0:string,1:string}
     */
    private function applyTabSort(
        string $tab,
        Request $request,
        EloquentBuilder $diaryQuery,
        EloquentBuilder $shiftQuery,
        EloquentBuilder $assignmentQuery,
        EloquentBuilder $vacationQuery,
    ): array {
        return match ($tab) {
            'diary' => SortableQuery::apply($diaryQuery, $request, [
                'mitarbeiter' => 'user_id',
                'status' => 'status',
                'von' => 'start_at',
                'bis' => 'end_at',
                'erstellt' => 'created_at',
            ], 'von', 'desc'),
            'bereitschaft' => SortableQuery::apply($shiftQuery, $request, [
                'mitarbeiter' => 'user_id',
                'von' => 'start_at',
                'bis' => 'end_at',
            ], 'von', 'desc'),
            'notdienst' => SortableQuery::apply($assignmentQuery, $request, [
                'mitarbeiter' => 'user_id',
                'von' => 'start_at',
                'bis' => 'end_at',
            ], 'von', 'desc'),
            'urlaub' => SortableQuery::apply($vacationQuery, $request, [
                'mitarbeiter' => 'user_id',
                'typ' => 'type',
                'status' => 'status',
                'von' => 'start_date',
                'bis' => 'end_date',
            ], 'von', 'desc'),
            default => ['', 'desc'],
        };
    }
}
