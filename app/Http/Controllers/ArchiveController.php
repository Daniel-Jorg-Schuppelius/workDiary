<?php

namespace App\Http\Controllers;

use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use App\Models\Vacation;
use App\Services\Archive\ArchiveService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ArchiveController extends Controller {
    public function index(Request $request): View {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        $tab = (string) $request->query('tab', 'diary');
        if (! in_array($tab, ['diary', 'bereitschaft', 'notdienst', 'urlaub'], true)) {
            $tab = 'diary';
        }

        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', '-1', '1', '2', '3'], true)) {
            $statusFilter = 'all';
        }

        $diaryQuery = DiaryEntry::query()
            ->select(['id', 'user_id', 'content', 'status', 'start_at', 'end_at', 'archived_at'])
            ->with('user:id,name')
            ->where('is_archived', true)
            ->orderByDesc('archived_at');

        $shiftQuery = OnCallShift::query()
            ->select(['id', 'user_id', 'start_at', 'end_at', 'note'])
            ->with('user:id,name')
            ->where('is_archived', true)
            ->orderByDesc('end_at');

        $assignmentQuery = EmergencyAssignment::query()
            ->select(['id', 'user_id', 'on_call_shift_id', 'start_at', 'end_at', 'reason'])
            ->with(['user:id,name', 'shift:id,start_at,end_at'])
            ->where('is_archived', true)
            ->orderByDesc('end_at');

        // Urlaub-Archiv: abgelehnt, storniert, oder genehmigt+abgelaufen
        $vacationQuery = Vacation::query()
            ->with('user:id,name')
            ->where(function ($q) {
                $q->whereIn('status', [Vacation::STATUS_REJECTED, Vacation::STATUS_CANCELLED])
                    ->orWhere(function ($q2) {
                        $q2->where('status', Vacation::STATUS_APPROVED)
                            ->where('end_date', '<', now()->toDateString());
                    });
            })
            ->orderByDesc('end_date');

        if (! $isAdmin) {
            $diaryQuery->where('user_id', $user->id);
            $shiftQuery->where('user_id', $user->id);
            $assignmentQuery->where('user_id', $user->id);
            $vacationQuery->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $uid = (int) $request->user_id;
            $diaryQuery->where('user_id', $uid);
            $shiftQuery->where('user_id', $uid);
            $assignmentQuery->where('user_id', $uid);
            $vacationQuery->where('user_id', $uid);
        }

        if ($request->filled('from')) {
            $diaryQuery->whereDate('end_at', '>=', $request->from);
            $shiftQuery->whereDate('end_at', '>=', $request->from);
            $assignmentQuery->whereDate('end_at', '>=', $request->from);
            $vacationQuery->whereDate('end_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $diaryQuery->whereDate('end_at', '<=', $request->to);
            $shiftQuery->whereDate('end_at', '<=', $request->to);
            $assignmentQuery->whereDate('end_at', '<=', $request->to);
            $vacationQuery->whereDate('end_date', '<=', $request->to);
        }
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
            'diary'        => (clone $diaryQuery)->count(),
            'bereitschaft' => (clone $shiftQuery)->count(),
            'notdienst'    => (clone $assignmentQuery)->count(),
            'urlaub'       => (clone $vacationQuery)->count(),
        ];

        if ($tab === 'urlaub') {
            $tabKpis = [
                'total'     => $counts['urlaub'],
                'rejected'  => (clone $vacationQuery)->where('status', Vacation::STATUS_REJECTED)->count(),
                'cancelled' => (clone $vacationQuery)->where('status', Vacation::STATUS_CANCELLED)->count(),
                'expired'   => (clone $vacationQuery)->where('status', Vacation::STATUS_APPROVED)
                    ->where('end_date', '<', now()->toDateString())->count(),
            ];
        } elseif ($tab === 'diary') {
            $base = clone $diaryQuery;
            $tabKpis = [
                'total'    => $counts['diary'],
                'erledigt' => (clone $base)->where('status', -1)->count(),
                'offen'    => (clone $base)->where('status', 2)->count(),
                'alert'    => (clone $base)->where('status', 3)->count(),
            ];
        } elseif ($tab === 'bereitschaft') {
            $base      = clone $shiftQuery;
            $durations = (clone $base)->get(['start_at', 'end_at'])
                ->map(fn(OnCallShift $r) => (int) $r->start_at->startOfDay()->diffInDays($r->end_at->startOfDay()) + 1);
            $tabKpis = [
                'total'   => $counts['bereitschaft'],
                'longest' => (int) ($durations->max() ?? 0),
                'avg'     => $durations->count() > 0 ? round($durations->avg(), 1) : 0,
                'users'   => (clone $base)->distinct()->count('user_id'),
            ];
        } else {
            $base      = clone $assignmentQuery;
            $durations = (clone $base)->get(['start_at', 'end_at'])
                ->map(fn(EmergencyAssignment $r) => (int) $r->start_at->startOfDay()->diffInDays($r->end_at->startOfDay()) + 1);
            $tabKpis = [
                'total'   => $counts['notdienst'],
                'longest' => (int) ($durations->max() ?? 0),
                'avg'     => $durations->count() > 0 ? round($durations->avg(), 1) : 0,
                'users'   => (clone $base)->distinct()->count('user_id'),
            ];
        }

        $users = $isAdmin
            ? User::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('archive.index', [
            'isAdmin'           => $isAdmin,
            'users'             => $users,
            'tab'               => $tab,
            'statusFilter'      => $statusFilter,
            'filters'           => $request->only('from', 'to', 'status', 'user_id', 'vtype', 'vstatus'),
            'counts'            => $counts,
            'tabKpis'           => $tabKpis,
            'diaryEntries'      => $diaryQuery->paginate(25, ['*'], 'dpage')->withQueryString(),
            'shiftEntries'      => $shiftQuery->paginate(25, ['*'], 'spage')->withQueryString(),
            'assignmentEntries' => $assignmentQuery->paginate(25, ['*'], 'apage')->withQueryString(),
            'vacationEntries'   => $vacationQuery->paginate(25, ['*'], 'vpage')->withQueryString(),
        ]);
    }

    public function run(ArchiveService $service): RedirectResponse {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_unless($user !== null && $user->isAdmin(), 403);

        $result = $service->run();

        return back()->with('success', __('Archivierung abgeschlossen: :total Datensätze (Tagebuch :diary, Bereitschaft :shifts, Notdienst :assignments).', [
            'total' => $result['total'],
            'diary' => $result['diary'],
            'shifts' => $result['shifts'],
            'assignments' => $result['assignments'],
        ]));
    }
}
