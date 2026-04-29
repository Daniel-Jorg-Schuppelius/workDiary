<?php

namespace App\Http\Controllers;

use App\Models\Legacy\LegacyArchiveDiaryEntry;
use App\Models\Legacy\LegacyArchiveNotdienst;
use App\Models\Legacy\LegacyArchiveOnCall;
use App\Models\Legacy\LegacyUser;
use App\Services\Legacy\LegacyArchiveService;
use App\Support\LegacyRoleResolver;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LegacyArchiveController extends Controller {
    public function week(Request $request): View {
        $this->ensureAdmin();

        $weekOffset = (int) $request->query('week', 0);
        $weekDate = trim((string) $request->query('week_date', ''));
        $baseMonday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $monday = $baseMonday->copy()->addWeeks($weekOffset);
        if (preg_match('/^(\d{4})-W(\d{2})$/', $weekDate, $matches) === 1) {
            $isoYear = (int) $matches[1];
            $isoWeek = (int) $matches[2];
            $monday = Carbon::now()->setISODate($isoYear, $isoWeek, 1)->startOfDay();
            $weekOffset = $baseMonday->diffInWeeks($monday, false);
        }
        $sunday = $monday->copy()->addDays(6)->endOfDay();

        $users = LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname']);

        $allEntries = LegacyArchiveDiaryEntry::query()
            ->where('bis', '>', $monday->copy()->startOfDay())
            ->where('von', '<', $sunday)
            ->get();

        $oncalls = LegacyArchiveOnCall::query()
            ->where('von', '<=', $sunday->toDateString())
            ->where('bis', '>=', $monday->toDateString())
            ->get();

        $notdiensts = LegacyArchiveNotdienst::query()
            ->where('von', '<=', $sunday->toDateString())
            ->where('bis', '>=', $monday->toDateString())
            ->get();

        $entriesByUserDay = [];
        foreach ($allEntries as $entry) {
            if (! $entry->von || ! $entry->bis) {
                continue;
            }
            $uid = (int) $entry->user;
            $cursor = $entry->von->copy()->startOfDay();
            $endDay = $entry->bis->copy()->startOfDay();
            while ($cursor->lte($endDay)) {
                $entriesByUserDay[$uid][$cursor->format('Y-m-d')][] = $entry;
                $cursor->addDay();
            }
        }

        $oncallByUserDay = [];
        foreach ($oncalls as $oc) {
            $uid = (int) $oc->user;
            $cursor = Carbon::parse($oc->von);
            $end = Carbon::parse($oc->bis);
            while ($cursor->lte($end)) {
                $oncallByUserDay[$uid][$cursor->format('Y-m-d')] = true;
                $cursor->addDay();
            }
        }

        $notdienstByUserDay = [];
        foreach ($notdiensts as $nd) {
            $uid = (int) $nd->user;
            $cursor = Carbon::parse($nd->von);
            $end = Carbon::parse($nd->bis);
            while ($cursor->lte($end)) {
                $notdienstByUserDay[$uid][$cursor->format('Y-m-d')] = true;
                $cursor->addDay();
            }
        }

        return view('legacy.archive.week', [
            'users' => $users,
            'monday' => $monday,
            'sunday' => $sunday,
            'weekOffset' => $weekOffset,
            'selectedWeek' => $monday->format('o-\\WW'),
            'entriesByUserDay' => $entriesByUserDay,
            'oncallByUserDay' => $oncallByUserDay,
            'notdienstByUserDay' => $notdienstByUserDay,
        ]);
    }

    public function index(Request $request): View {
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId(Auth::user());
        $isAdmin = LegacyRoleResolver::isAdmin(Auth::user());

        $diaryQuery = LegacyArchiveDiaryEntry::query()->with('mitarbeiter:id,uname')->orderByDesc('bis');
        $onCallQuery = LegacyArchiveOnCall::query()->with('mitarbeiter:id,uname')->orderByDesc('bis');
        $notdienstQuery = LegacyArchiveNotdienst::query()->with('mitarbeiter:id,uname')->orderByDesc('bis');

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
            'users' => LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname']),
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
            'user' => ['nullable', 'integer', 'min:4'],
        ]);

        $result = $service->archiveOlderThanMonths((int) $data['months'], isset($data['user']) ? (int) $data['user'] : null);

        return redirect()->route('legacy.archive.index')->with(
            'success',
            'Archivierung abgeschlossen: ' . $result['total'] . ' Datensätze verschoben (Aufträge ' . $result['diary'] . ', Bereitschaft ' . $result['oncall'] . ', Notdienst ' . $result['notdienst'] . ').'
        );
    }

    private function ensureAdmin(): void {
        abort_if(! LegacyRoleResolver::isAdmin(Auth::user()), 403);
    }
}
