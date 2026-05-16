<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyCallcenterController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Http\Requests\LegacyCallcenterLoginRequest;
use App\Legacy\Models\LegacyDiaryEntry;
use App\Legacy\Models\LegacyNotdienst;
use App\Legacy\Models\LegacyOnCall;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LegacyCallcenterController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function __construct(private readonly HolidayService $holidayService) {}

    public function showLoginForm(): View
    {
        return view('legacy.callcenter.login');
    }

    public function login(LegacyCallcenterLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'username' => __('Zu viele Anmeldeversuche. Bitte in :seconds Sekunden erneut versuchen.', ['seconds' => $seconds]),
            ])->onlyInput('username');
        }

        if (! filled(config('database.connections.legacy.database'))) {
            return back()->withErrors([
                'username' => __('Legacy-Datenbank ist nicht konfiguriert.'),
            ])->onlyInput('username');
        }

        try {
            $callUser = DB::connection('legacy')
                ->table('calluser')
                ->select(['uname'])
                ->where('uname', $credentials['username'])
                ->where('userpw', $credentials['password'])
                ->first();
        } catch (\Throwable) {
            $callUser = null;
        }

        if (! $callUser) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors([
                'username' => __('Nutzername oder Passwort ist falsch.'),
            ])->onlyInput('username');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->put('legacy_callcenter_user', (string) $callUser->uname);

        return redirect()->route('legacy.callcenter.notdienst');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('legacy_callcenter_user');
        $request->session()->regenerateToken();

        return redirect()->route('legacy.callcenter.login');
    }

    public function notdienstPlan(Request $request): View
    {
        $weekOffset = (int) $request->query('week', 0);
        $today = Carbon::today();
        $start = $today->copy()->subDay()->addWeeks($weekOffset);
        $days = collect(range(0, 6))->map(fn (int $offset) => $start->copy()->addDays($offset));

        $rangeStart = $start->copy()->startOfDay();
        $rangeEnd = $start->copy()->addDays(6)->endOfDay();

        $holidayService = $this->holidayService;
        $holidayMap = $days->mapWithKeys(fn (Carbon $day) => [$day->format('Y-m-d') => $holidayService->nameFor($day)])->all();

        $notdienstItems = LegacyNotdienst::query()
            ->select(['id', 'user', 'von', 'bis'])
            ->with('mitarbeiter:id,uname,email')
            ->whereDate('von', '<=', $rangeEnd->toDateString())
            ->whereDate('bis', '>=', $rangeStart->toDateString())
            ->orderBy('von')
            ->get();

        $bereitschaftItems = LegacyOnCall::query()
            ->select(['id', 'user', 'von', 'bis'])
            ->with('mitarbeiter:id,uname,email')
            ->whereDate('von', '<=', $rangeEnd->toDateString())
            ->whereDate('bis', '>=', $rangeStart->toDateString())
            ->orderBy('von')
            ->get();

        $notdienstByDay = $this->mapDutyByDay($days, $notdienstItems, $today, $holidayMap);
        $bereitschaftByDay = $this->mapDutyByDay($days, $bereitschaftItems, $today, $holidayMap);

        $todayNotdienst = collect($notdienstByDay)->firstWhere('isToday', true);
        $todayBereitschaft = collect($bereitschaftByDay)->firstWhere('isToday', true);
        $tomorrowNotdienst = collect($notdienstByDay)->firstWhere(fn (array $d) => $d['date']->isSameDay($today->copy()->addDay()));
        $tomorrowBereitschaft = collect($bereitschaftByDay)->firstWhere(fn (array $d) => $d['date']->isSameDay($today->copy()->addDay()));

        $weekendNotdienst = collect($notdienstByDay)->filter(fn (array $d) => $d['isWeekend'] || $d['isHoliday'])->values();
        $weekendBereitschaft = collect($bereitschaftByDay)->filter(fn (array $d) => $d['isWeekend'] || $d['isHoliday'])->values();

        $openIssues = LegacyDiaryEntry::query()
            ->select(['id', 'user', 'inhalt', 'von', 'bis', 'gelesen', 'aktuell'])
            ->with('author:id,uname')
            ->whereIn('gelesen', [2, 3])
            ->where('bis', '>=', $today)
            ->orderByDesc('gelesen')
            ->orderBy('bis')
            ->limit(15)
            ->get();

        // Status-KPIs aktiver Tagebuch-Einträge (bis >= heute) plus erledigt der letzten 7 Tage
        $statusCounts = [
            'open' => LegacyDiaryEntry::query()->where('gelesen', 2)->where('bis', '>=', $today)->count(),
            'alert' => LegacyDiaryEntry::query()->where('gelesen', 3)->where('bis', '>=', $today)->count(),
            'progress' => LegacyDiaryEntry::query()->where('gelesen', 1)->where('bis', '>=', $today)->count(),
            'doneRecent' => LegacyDiaryEntry::query()->where('gelesen', -1)->where('bis', '>=', $today->copy()->subDays(7))->count(),
        ];

        // Erweiterte Lage-Indikatoren
        $overdueCount = LegacyDiaryEntry::query()
            ->whereIn('gelesen', [2, 3])
            ->where('bis', '<', $today)
            ->count();

        $dueTodayCount = LegacyDiaryEntry::query()
            ->whereIn('gelesen', [2, 3])
            ->whereDate('bis', $today->toDateString())
            ->count();

        $dueNext7Count = LegacyDiaryEntry::query()
            ->whereIn('gelesen', [2, 3])
            ->whereBetween('bis', [$today->copy()->addDay()->startOfDay(), $today->copy()->addDays(7)->endOfDay()])
            ->count();

        // 14-Tage-Trend: neue Einträge je Tag (Sparkline-Daten)
        $trendStart = $today->copy()->subDays(13);
        $trendRaw = LegacyDiaryEntry::query()
            ->selectRaw('DATE(von) as d, COUNT(*) as c')
            ->where('von', '>=', $trendStart)
            ->groupBy('d')
            ->pluck('c', 'd');

        $trend = collect();
        for ($i = 0; $i < 14; $i++) {
            $day = $trendStart->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $trend->push([
                'date' => $day,
                'count' => (int) ($trendRaw[$key] ?? 0),
            ]);
        }
        $trendMax = max(1, (int) $trend->max('count'));

        // Top-Autoren mit offenen/Eskalations-Meldungen
        $topAuthors = LegacyDiaryEntry::query()
            ->select(['user', DB::raw('COUNT(*) as cnt')])
            ->with('author:id,uname')
            ->whereIn('gelesen', [2, 3])
            ->where('bis', '>=', $today)
            ->whereNotNull('user')
            ->groupBy('user')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        // Status-Mix (für kompakte Verteilungs-Anzeige)
        $statusTotal = (int) array_sum([
            $statusCounts['alert'],
            $statusCounts['open'],
            $statusCounts['progress'],
        ]);

        // Nächste Feiertage (kommende 30 Tage), maximal 5
        $upcomingHolidays = $this->collectUpcomingHolidays($today, 30, 5);

        return view('legacy.callcenter.notdienst', [
            'notdienstByDay' => $notdienstByDay,
            'bereitschaftByDay' => $bereitschaftByDay,
            'todayNotdienst' => $todayNotdienst,
            'todayBereitschaft' => $todayBereitschaft,
            'tomorrowNotdienst' => $tomorrowNotdienst,
            'tomorrowBereitschaft' => $tomorrowBereitschaft,
            'weekendNotdienst' => $weekendNotdienst,
            'weekendBereitschaft' => $weekendBereitschaft,
            'openIssues' => $openIssues,
            'statusCounts' => $statusCounts,
            'statusTotal' => $statusTotal,
            'overdueCount' => $overdueCount,
            'dueTodayCount' => $dueTodayCount,
            'dueNext7Count' => $dueNext7Count,
            'trend' => $trend,
            'trendMax' => $trendMax,
            'topAuthors' => $topAuthors,
            'upcomingHolidays' => $upcomingHolidays,
            'weekOffset' => $weekOffset,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'callcenterUser' => (string) $request->session()->get('legacy_callcenter_user', ''),
            'holidayMap' => $holidayMap,
            'today' => $today,
        ]);
    }

    /**
     * @return array<int, array{date: Carbon, name: string, daysAway: int}>
     */
    private function collectUpcomingHolidays(Carbon $today, int $windowDays, int $limit): array
    {
        $result = [];
        $years = [(int) $today->year];
        if ((int) $today->copy()->addDays($windowDays)->year !== $years[0]) {
            $years[] = (int) $today->copy()->addDays($windowDays)->year;
        }

        foreach ($years as $year) {
            foreach ($this->holidayService->forYear($year) as $dateStr => $name) {
                $date = Carbon::parse($dateStr)->startOfDay();
                $diff = $today->diffInDays($date, false);
                if ($diff >= 0 && $diff <= $windowDays) {
                    $result[] = ['date' => $date, 'name' => $name, 'daysAway' => (int) $diff];
                }
            }
        }

        usort($result, fn (array $a, array $b) => $a['date']->timestamp <=> $b['date']->timestamp);

        return array_slice($result, 0, $limit);
    }

    private function throttleKey(Request $request): string
    {
        return 'legacy-callcenter:'.mb_strtolower((string) $request->input('username', '')).'|'.$request->ip();
    }

    /**
     * @template TDuty of LegacyNotdienst|LegacyOnCall
     *
     * @param  Collection<int, Carbon>  $days
     * @param  \Illuminate\Database\Eloquent\Collection<int, TDuty>  $items
     * @param  array<string, string|null>  $holidayMap
     * @return array<int, array<string, mixed>>
     */
    private function mapDutyByDay(Collection $days, \Illuminate\Database\Eloquent\Collection $items, Carbon $today, array $holidayMap = []): array
    {
        return $days->map(function (Carbon $day) use ($items, $today, $holidayMap): array {
            $match = $items->first(function (LegacyNotdienst|LegacyOnCall $item) use ($day): bool {
                return (bool) ($item->von && $item->bis && $item->von->copy()->startOfDay()->lte($day) && $item->bis->copy()->endOfDay()->gte($day));
            });

            $key = $day->format('Y-m-d');
            $dow = (int) $day->dayOfWeek;

            return [
                'date' => $day,
                'isToday' => $day->isSameDay($today),
                'isWeekend' => $dow === 0 || $dow === 6,
                'isSunday' => $dow === 0,
                'isSaturday' => $dow === 6,
                'holidayName' => $holidayMap[$key] ?? null,
                'isHoliday' => ($holidayMap[$key] ?? null) !== null,
                'user' => optional($match?->mitarbeiter)->uname,
                'email' => optional($match?->mitarbeiter)->email,
                'von' => $match?->von,
                'bis' => $match?->bis,
            ];
        })->all();
    }
}
