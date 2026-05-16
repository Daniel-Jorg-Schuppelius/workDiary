<?php

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

        // ── Aufträge (Diary) ─────────────────────────────────────────────────
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

        // ── Bereitschaft ─────────────────────────────────────────────────────
        $shiftQuery = OnCallShift::query()
            ->with('user:id,name')
            ->where('is_archived', false)
            ->orderByDesc('start_at');

        $shiftQuery->whereDate('start_at', '>=', $rangeFrom);
        $shiftQuery->whereDate('start_at', '<=', $rangeTo);

        // ── Urlaub ───────────────────────────────────────────────────────────
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

        // ── Notdienst ────────────────────────────────────────────────────────
        $assignmentQuery = EmergencyAssignment::query()
            ->with(['user:id,name', 'shift:id,start_at,end_at,user_id'])
            ->where('is_archived', false)
            ->orderByDesc('start_at');

        $assignmentQuery->whereDate('start_at', '>=', $rangeFrom);
        $assignmentQuery->whereDate('start_at', '<=', $rangeTo);

        // ── Counts ───────────────────────────────────────────────────────────
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

        $diaryCounts = [
            'all' => (int) ($row->cnt_all ?? 0),
            'open' => (int) ($row->cnt_open ?? 0),
            'alert' => (int) ($row->cnt_alert ?? 0),
            'done' => (int) ($row->cnt_done ?? 0),
        ];

        $tabCounts = [
            'diary' => (clone $diaryQuery)->count(),
            'bereitschaft' => (clone $shiftQuery)->count(),
            'notdienst' => (clone $assignmentQuery)->count(),
            'urlaub' => (clone $vacationQuery)->count(),
        ];

        // KPIs für Bereitschaft- und Notdienst-Tabs
        $shiftDurations = (clone $shiftQuery)->get(['start_at', 'end_at'])
            ->map(fn($r) => $r->start_at && $r->end_at
                ? (int) $r->start_at->startOfDay()->diffInDays($r->end_at->startOfDay()) + 1
                : 0);
        $shiftKpis = [
            'total' => $tabCounts['bereitschaft'],
            'longest' => (int) ($shiftDurations->max() ?? 0),
            'avg' => $shiftDurations->count() > 0 ? round($shiftDurations->avg(), 1) : 0,
            'users' => (clone $shiftQuery)->distinct()->count('user_id'),
        ];

        $assignmentDurations = (clone $assignmentQuery)->get(['start_at', 'end_at'])
            ->map(fn($r) => $r->start_at && $r->end_at
                ? (int) $r->start_at->startOfDay()->diffInDays($r->end_at->startOfDay()) + 1
                : 0);
        $assignmentKpis = [
            'total' => $tabCounts['notdienst'],
            'longest' => (int) ($assignmentDurations->max() ?? 0),
            'avg' => $assignmentDurations->count() > 0 ? round($assignmentDurations->avg(), 1) : 0,
            'users' => (clone $assignmentQuery)->distinct()->count('user_id'),
        ];

        $vacationKpis = [
            'total' => (clone $vacationQuery)->count(),
            'pending' => (clone $vacationQuery)->where('status', Vacation::STATUS_PENDING)->count(),
            'approved' => (clone $vacationQuery)->where('status', Vacation::STATUS_APPROVED)
                ->where('end_date', '>=', now()->startOfYear())->count(),
            'rejected' => (clone $vacationQuery)->where('status', Vacation::STATUS_REJECTED)->count(),
        ];

        $allTags = Tag::orderBy('name')->get(['id', 'name', 'color']);
        $users = $isAdmin ? User::query()->orderBy('name')->get(['id', 'name']) : collect();
        $filters = $request->only('status', 'mine', 'q', 'tag', 'vtype', 'vstatus', 'user_id');
        $filters['from'] = $rangeFrom;
        $filters['to'] = $rangeTo;

        // Sortierung pro aktivem Tab anwenden.
        $sort = '';
        $dir = 'desc';
        if ($tab === 'diary') {
            [$sort, $dir] = SortableQuery::apply($diaryQuery, $request, [
                'mitarbeiter' => 'user_id',
                'status' => 'status',
                'von' => 'start_at',
                'bis' => 'end_at',
                'erstellt' => 'created_at',
            ], 'von', 'desc');
        } elseif ($tab === 'bereitschaft') {
            [$sort, $dir] = SortableQuery::apply($shiftQuery, $request, [
                'mitarbeiter' => 'user_id',
                'von' => 'start_at',
                'bis' => 'end_at',
            ], 'von', 'desc');
        } elseif ($tab === 'notdienst') {
            [$sort, $dir] = SortableQuery::apply($assignmentQuery, $request, [
                'mitarbeiter' => 'user_id',
                'von' => 'start_at',
                'bis' => 'end_at',
            ], 'von', 'desc');
        } elseif ($tab === 'urlaub') {
            [$sort, $dir] = SortableQuery::apply($vacationQuery, $request, [
                'mitarbeiter' => 'user_id',
                'typ' => 'type',
                'status' => 'status',
                'von' => 'start_date',
                'bis' => 'end_date',
            ], 'von', 'desc');
        }

        return view('duties.index', [
            'tab' => $tab,
            'filters' => $filters,
            'tabCounts' => $tabCounts,
            'diaryCounts' => $diaryCounts,
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
}
