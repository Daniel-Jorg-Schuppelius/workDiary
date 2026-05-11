<?php

namespace App\Http\Controllers;

use App\Models\Legacy\LegacyDiaryEntry;
use App\Models\Legacy\LegacyNotdienst;
use App\Models\Legacy\LegacyOnCall;
use App\Services\HolidayService;
use App\Support\LegacyRoleResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LegacyOverviewController extends Controller {
    public function index(HolidayService $holidayService): View {
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId(Auth::user());
        $isAdmin = LegacyRoleResolver::isAdmin(Auth::user());

        $diaryQuery = LegacyDiaryEntry::query();
        if (! $isAdmin && $legacyUserId > 3) {
            $diaryQuery->where('user', $legacyUserId);
        }

        $countsRow = (clone $diaryQuery)->selectRaw(
            'COUNT(*) as cnt_all,'
                . 'SUM(gelesen = 2) as cnt_open,'
                . 'SUM(gelesen = 3) as cnt_alert,'
                . 'SUM(gelesen = 1) as cnt_work,'
                . 'SUM(gelesen = -1) as cnt_done'
        )->first()?->getAttributes() ?? [];

        $statusCounts = [
            'all' => (int) ($countsRow['cnt_all'] ?? 0),
            'open' => (int) ($countsRow['cnt_open'] ?? 0),
            'alert' => (int) ($countsRow['cnt_alert'] ?? 0),
            'work' => (int) ($countsRow['cnt_work'] ?? 0),
            'done' => (int) ($countsRow['cnt_done'] ?? 0),
        ];

        $today = Carbon::today();

        $oncallToday = LegacyOnCall::query()
            ->with('mitarbeiter:id,uname')
            ->whereDate('von', '<=', $today)
            ->whereDate('bis', '>=', $today)
            ->orderBy('von')
            ->get(['id', 'user', 'von', 'bis']);

        $notdienstToday = LegacyNotdienst::query()
            ->with('mitarbeiter:id,uname')
            ->whereDate('von', '<=', $today)
            ->whereDate('bis', '>=', $today)
            ->orderBy('von')
            ->get(['id', 'user', 'von', 'bis']);

        $year = (int) $today->year;
        $holidayMap = $holidayService->forYear($year) + $holidayService->forYear($year + 1);
        $upcomingHolidays = collect($holidayMap)
            ->map(fn($name, $date) => ['date' => Carbon::parse($date), 'name' => $name])
            ->filter(fn($h) => $h['date']->gte($today))
            ->sortBy(fn($h) => $h['date']->getTimestamp())
            ->take(8)
            ->values();

        return view('legacy.overview.index', [
            'isAdmin' => $isAdmin,
            'statusCounts' => $statusCounts,
            'oncallToday' => $oncallToday,
            'notdienstToday' => $notdienstToday,
            'upcomingHolidays' => $upcomingHolidays,
            'todayHolidayName' => $holidayService->nameFor($today),
            'today' => $today,
        ]);
    }
}
