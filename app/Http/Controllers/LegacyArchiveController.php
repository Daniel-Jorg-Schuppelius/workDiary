<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresLegacyAdmin;
use App\Models\Legacy\LegacyArchiveDiaryEntry;
use App\Models\Legacy\LegacyArchiveNotdienst;
use App\Models\Legacy\LegacyArchiveOnCall;
use App\Models\Vacation;
use App\Services\Legacy\LegacyArchiveService;
use App\Services\Legacy\LegacyWeekCalendarService;
use App\Services\HolidayService;
use App\Support\LegacyRoleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LegacyArchiveController extends Controller {
    use RequiresLegacyAdmin;

    public function week(Request $request, LegacyWeekCalendarService $calendar, HolidayService $holidays): View {
        $this->ensureAdmin();

        $weekOffset = (int) $request->query('week', 0);
        $weekDate = trim((string) $request->query('week_date', ''));
        ['monday' => $monday, 'sunday' => $sunday, 'weekOffset' => $weekOffset, 'selectedWeek' => $selectedWeek] = $calendar->resolveWindow($weekOffset, $weekDate);

        $users = $this->legacyUsersForSelect();

        $allEntries = LegacyArchiveDiaryEntry::query()
            ->select(['id', 'user', 'von', 'bis', 'inhalt', 'gelesen', 'aktuell'])
            ->where('bis', '>', $monday->copy()->startOfDay())
            ->where('von', '<', $sunday)
            ->get();

        $oncalls = LegacyArchiveOnCall::query()
            ->select(['id', 'user', 'von', 'bis'])
            ->where('von', '<=', $sunday->toDateString())
            ->where('bis', '>=', $monday->toDateString())
            ->get();

        $notdiensts = LegacyArchiveNotdienst::query()
            ->select(['id', 'user', 'von', 'bis'])
            ->where('von', '<=', $sunday->toDateString())
            ->where('bis', '>=', $monday->toDateString())
            ->get();

        [
            'entriesByUserDay' => $entriesByUserDay,
            'oncallByUserDay' => $oncallByUserDay,
            'notdienstByUserDay' => $notdienstByUserDay,
        ] = $calendar->buildWeekMaps($allEntries, $oncalls, $notdiensts);

        return view('legacy.archive.week', [
            'users' => $users,
            'monday' => $monday,
            'sunday' => $sunday,
            'weekOffset' => $weekOffset,
            'selectedWeek' => $selectedWeek,
            'days' => collect(range(0, 6))->map(fn($i) => $monday->copy()->addDays($i)),
            'hours' => range(7, 20),
            'entriesByUserDay' => $entriesByUserDay,
            'oncallByUserDay' => $oncallByUserDay,
            'notdienstByUserDay' => $notdienstByUserDay,
            'holidays' => $holidays,
        ]);
    }

    public function index(Request $request): View {
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId(Auth::user());
        $isAdmin = LegacyRoleResolver::isAdmin(Auth::user());

        $tab = (string) $request->query('tab', 'auftraege');
        if (! in_array($tab, ['auftraege', 'bereitschaft', 'notdienst', 'urlaub'], true)) {
            $tab = 'auftraege';
        }

        /** @var \App\Models\User $currentUser */
        $currentUser     = Auth::user();
        $vacationIsAdmin = $currentUser->isAdmin();

        $statusFilter = (string) $request->query('status', 'all');
        $allowedStatus = ['all', '-1', '1', '2', '3'];
        if (! in_array($statusFilter, $allowedStatus, true)) {
            $statusFilter = 'all';
        }

        $diaryQuery = LegacyArchiveDiaryEntry::query()
            ->select(['id', 'user', 'inhalt', 'von', 'bis', 'gelesen'])
            ->with('mitarbeiter:id,uname')
            ->orderByDesc('bis');
        $onCallQuery = LegacyArchiveOnCall::query()
            ->select(['id', 'user', 'von', 'bis'])
            ->with('mitarbeiter:id,uname')
            ->orderByDesc('bis');
        $notdienstQuery = LegacyArchiveNotdienst::query()
            ->select(['id', 'user', 'von', 'bis'])
            ->with('mitarbeiter:id,uname')
            ->orderByDesc('bis');

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

        if (! $vacationIsAdmin) {
            $vacationQuery->where('user_id', $currentUser->id);
        } elseif ($request->boolean('mine')) {
            $vacationQuery->where('user_id', $currentUser->id);
        }
        if ($request->filled('vtype')) {
            $vacationQuery->where('type', $request->vtype);
        }
        if ($request->filled('vstatus')) {
            $vacationQuery->where('status', $request->vstatus);
        }

        if (! $isAdmin && $legacyUserId > 3) {
            $diaryQuery->where('user', $legacyUserId);
            $onCallQuery->where('user', $legacyUserId);
            $notdienstQuery->where('user', $legacyUserId);
        } elseif ($request->filled('user')) {
            $targetUser = (int) $request->user;
            $diaryQuery->where('user', $targetUser);
            $onCallQuery->where('user', $targetUser);
            $notdienstQuery->where('user', $targetUser);
        }

        if ($request->filled('from')) {
            $diaryQuery->whereDate('bis', '>=', $request->from);
            $onCallQuery->whereDate('bis', '>=', $request->from);
            $notdienstQuery->whereDate('bis', '>=', $request->from);
            $vacationQuery->whereDate('end_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $diaryQuery->whereDate('bis', '<=', $request->to);
            $onCallQuery->whereDate('bis', '<=', $request->to);
            $notdienstQuery->whereDate('bis', '<=', $request->to);
            $vacationQuery->whereDate('end_date', '<=', $request->to);
        }

        if ($tab === 'auftraege' && $statusFilter !== 'all') {
            $diaryQuery->where('gelesen', (int) $statusFilter);
        }

        $counts = [
            'auftraege'    => (clone $diaryQuery)->toBase()->getCountForPagination(),
            'bereitschaft' => (clone $onCallQuery)->toBase()->getCountForPagination(),
            'notdienst'    => (clone $notdienstQuery)->toBase()->getCountForPagination(),
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
        } else {
            // Per-Tab KPIs (vom aktiven Tab abhängig, Filter wirken mit)
            /** @var \Illuminate\Database\Eloquent\Builder<LegacyArchiveDiaryEntry> $diaryQuery */
            /** @var \Illuminate\Database\Eloquent\Builder<LegacyArchiveOnCall> $onCallQuery */
            /** @var \Illuminate\Database\Eloquent\Builder<LegacyArchiveNotdienst> $notdienstQuery */
            $tabKpis = $this->buildTabKpis($tab, $diaryQuery, $onCallQuery, $notdienstQuery);
        }

        return view('legacy.archive.index', [
            'isAdmin'         => $isAdmin,
            'vacationIsAdmin' => $vacationIsAdmin,
            'legacyUserId'    => $legacyUserId,
            'users'           => $this->legacyUsersForSelect(),
            'filters'         => $request->only('user', 'from', 'to', 'status', 'vtype', 'vstatus'),
            'tab'             => $tab,
            'statusFilter'    => $statusFilter,
            'counts'          => $counts,
            'tabKpis'         => $tabKpis,
            'diaryEntries'    => $diaryQuery->paginate(25, ['*'], 'dpage')->withQueryString(),
            'onCallEntries'   => $onCallQuery->paginate(25, ['*'], 'opage')->withQueryString(),
            'notdienstEntries' => $notdienstQuery->paginate(25, ['*'], 'npage')->withQueryString(),
            'vacationEntries' => $vacationQuery->paginate(25, ['*'], 'vpage')->withQueryString(),
        ]);
    }

    public function show(LegacyArchiveDiaryEntry $entry): View|\Illuminate\Http\Response {
        $entry->load('mitarbeiter:id,uname');

        if (request()->boolean('dialog')) {
            return response(view('legacy.archive._show_dialog', ['entry' => $entry]));
        }

        return view('legacy.archive.show', ['entry' => $entry]);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<LegacyArchiveDiaryEntry> $diaryQuery
     * @param \Illuminate\Database\Eloquent\Builder<LegacyArchiveOnCall> $onCallQuery
     * @param \Illuminate\Database\Eloquent\Builder<LegacyArchiveNotdienst> $notdienstQuery
     * @return array<string, int|string>
     */
    private function buildTabKpis(string $tab, $diaryQuery, $onCallQuery, $notdienstQuery): array {
        if ($tab === 'auftraege') {
            $base = (clone $diaryQuery);

            return [
                'total'    => (clone $base)->toBase()->getCountForPagination(),
                'erledigt' => (clone $base)->where('gelesen', -1)->toBase()->getCountForPagination(),
                'offen'    => (clone $base)->where('gelesen', 2)->toBase()->getCountForPagination(),
                'alert'    => (clone $base)->where('gelesen', 3)->toBase()->getCountForPagination(),
            ];
        }

        $query = $tab === 'bereitschaft' ? $onCallQuery : $notdienstQuery;
        $base  = clone $query;

        $durations = (clone $base)->get(['von', 'bis'])->map(function ($row) {
            if (! $row->von || ! $row->bis) {
                return 0;
            }
            return (int) $row->von->copy()->startOfDay()->diffInDays($row->bis->copy()->startOfDay()) + 1;
        });

        return [
            'total'   => (clone $base)->toBase()->getCountForPagination(),
            'longest' => (int) ($durations->max() ?? 0),
            'avg'     => $durations->count() > 0 ? round($durations->avg(), 1) : 0,
            'users'   => (clone $base)->distinct()->count('user'),
        ];
    }

    public function run(Request $request, LegacyArchiveService $service): RedirectResponse {
        $this->ensureAdmin();

        $data = $request->validate([
            'months' => ['required', 'integer', 'in:3,6,9,12'],
            'user' => ['nullable', 'integer', 'min:4', 'exists:legacy.user,id'],
        ]);

        $result = $service->archiveOlderThanMonths((int) $data['months'], isset($data['user']) ? (int) $data['user'] : null);

        return redirect()->route('legacy.archive.index')->with(
            'success',
            'Archivierung abgeschlossen: ' . $result['total'] . ' Datensaetze verschoben (Auftraege ' . $result['diary'] . ', Bereitschaft ' . $result['oncall'] . ', Notdienst ' . $result['notdienst'] . ').'
        );
    }
}
