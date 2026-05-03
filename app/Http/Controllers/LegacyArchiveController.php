<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresLegacyAdmin;
use App\Models\Legacy\LegacyArchiveDiaryEntry;
use App\Models\Legacy\LegacyArchiveNotdienst;
use App\Models\Legacy\LegacyArchiveOnCall;
use App\Services\Legacy\LegacyArchiveService;
use App\Services\Legacy\LegacyWeekCalendarService;
use App\Support\LegacyRoleResolver;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LegacyArchiveController extends Controller {
    use RequiresLegacyAdmin;

    public function week(Request $request, LegacyWeekCalendarService $calendar): View {
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
        ]);
    }

    public function index(Request $request): View {
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId(Auth::user());
        $isAdmin = LegacyRoleResolver::isAdmin(Auth::user());

        $diaryQuery = LegacyArchiveDiaryEntry::query()
            ->select(['id', 'user', 'inhalt', 'bis'])
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

        return view('legacy.archive.index', [
            'isAdmin' => $isAdmin,
            'legacyUserId' => $legacyUserId,
            'users' => $this->legacyUsersForSelect(),
            'filters' => $request->only('user', 'from', 'to'),
            'diaryEntries' => $diaryQuery->paginate(20, ['*'], 'dpage')->withQueryString(),
            'onCallEntries' => $onCallQuery->paginate(20, ['*'], 'opage')->withQueryString(),
            'notdienstEntries' => $notdienstQuery->paginate(20, ['*'], 'npage')->withQueryString(),
        ]);
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
