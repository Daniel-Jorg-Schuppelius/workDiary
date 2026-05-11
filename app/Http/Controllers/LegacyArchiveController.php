<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresLegacyAdmin;
use App\Models\Legacy\LegacyArchiveDiaryEntry;
use App\Models\Legacy\LegacyArchiveNotdienst;
use App\Models\Legacy\LegacyArchiveOnCall;
use App\Services\Legacy\LegacyArchiveService;
use App\Services\Legacy\LegacyWeekCalendarService;
use App\Services\HolidayService;
use App\Support\LegacyRoleResolver;
use Carbon\Carbon;
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
        if (! in_array($tab, ['auftraege', 'bereitschaft', 'notdienst'], true)) {
            $tab = 'auftraege';
        }

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
        }

        if ($request->filled('to')) {
            $diaryQuery->whereDate('bis', '<=', $request->to);
            $onCallQuery->whereDate('bis', '<=', $request->to);
            $notdienstQuery->whereDate('bis', '<=', $request->to);
        }

        if ($tab === 'auftraege' && $statusFilter !== 'all') {
            $diaryQuery->where('gelesen', (int) $statusFilter);
        }

        $counts = [
            'auftraege' => (clone $diaryQuery)->toBase()->getCountForPagination(),
            'bereitschaft' => (clone $onCallQuery)->toBase()->getCountForPagination(),
            'notdienst' => (clone $notdienstQuery)->toBase()->getCountForPagination(),
        ];

        // Per-Tab KPIs (vom aktiven Tab abhängig, Filter wirken mit)
        $tabKpis = $this->buildTabKpis($tab, $diaryQuery, $onCallQuery, $notdienstQuery);

        return view('legacy.archive.index', [
            'isAdmin' => $isAdmin,
            'legacyUserId' => $legacyUserId,
            'users' => $this->legacyUsersForSelect(),
            'filters' => $request->only('user', 'from', 'to', 'status'),
            'tab' => $tab,
            'statusFilter' => $statusFilter,
            'counts' => $counts,
            'tabKpis' => $tabKpis,
            'diaryEntries' => $diaryQuery->paginate(25, ['*'], 'dpage')->withQueryString(),
            'onCallEntries' => $onCallQuery->paginate(25, ['*'], 'opage')->withQueryString(),
            'notdienstEntries' => $notdienstQuery->paginate(25, ['*'], 'npage')->withQueryString(),
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
        $today = Carbon::today();
        $cut7 = $today->copy()->subDays(7)->toDateString();
        $cut30 = $today->copy()->subDays(30)->toDateString();

        if ($tab === 'auftraege') {
            $base = (clone $diaryQuery);

            return [
                'total'  => (clone $base)->toBase()->getCountForPagination(),
                'last7'  => (clone $base)->whereDate('bis', '>=', $cut7)->toBase()->getCountForPagination(),
                'last30' => (clone $base)->whereDate('bis', '>=', $cut30)->toBase()->getCountForPagination(),
                'alert'  => (clone $base)->where('gelesen', 3)->toBase()->getCountForPagination(),
            ];
        }

        $query = $tab === 'bereitschaft' ? $onCallQuery : $notdienstQuery;
        $base = clone $query;

        // Längste Schicht: Tage zwischen von/bis (inklusiv)
        $longest = (clone $base)
            ->get(['von', 'bis'])
            ->map(function ($row) {
                if (! $row->von || ! $row->bis) {
                    return 0;
                }
                return (int) $row->von->copy()->startOfDay()->diffInDays($row->bis->copy()->startOfDay()) + 1;
            })
            ->max() ?? 0;

        return [
            'total'   => (clone $base)->toBase()->getCountForPagination(),
            'last7'   => (clone $base)->whereDate('bis', '>=', $cut7)->toBase()->getCountForPagination(),
            'last30'  => (clone $base)->whereDate('bis', '>=', $cut30)->toBase()->getCountForPagination(),
            'longest' => (int) $longest,
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
