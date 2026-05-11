<?php

namespace App\Http\Controllers;

use App\Http\Requests\LegacyCallcenterLoginRequest;
use App\Models\Legacy\LegacyDiaryEntry;
use App\Models\Legacy\LegacyNotdienst;
use App\Models\Legacy\LegacyOnCall;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LegacyCallcenterController extends Controller {
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function __construct(private readonly HolidayService $holidayService) {
    }

    public function showLoginForm(): View {
        return view('legacy.callcenter.login');
    }

    public function login(LegacyCallcenterLoginRequest $request): RedirectResponse {
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

    public function logout(Request $request): RedirectResponse {
        $request->session()->forget('legacy_callcenter_user');
        $request->session()->regenerateToken();

        return redirect()->route('legacy.callcenter.login');
    }

    public function notdienstPlan(Request $request): View {
        $weekOffset = (int) $request->query('week', 0);
        $today = Carbon::today();
        $start = $today->copy()->subDay()->addWeeks($weekOffset);
        $days = collect(range(0, 6))->map(fn(int $offset) => $start->copy()->addDays($offset));

        $rangeStart = $start->copy()->startOfDay();
        $rangeEnd = $start->copy()->addDays(6)->endOfDay();

        $holidayService = $this->holidayService;
        $holidayMap = $days->mapWithKeys(fn(Carbon $day) => [$day->format('Y-m-d') => $holidayService->nameFor($day)])->all();

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

        $openIssues = LegacyDiaryEntry::query()
            ->select(['id', 'user', 'inhalt', 'von', 'bis', 'gelesen'])
            ->with('author:id,uname')
            ->whereIn('gelesen', [2, 3])
            ->where('bis', '>=', $today)
            ->orderByDesc('gelesen')
            ->orderBy('bis')
            ->limit(10)
            ->get();

        return view('legacy.callcenter.notdienst', [
            'notdienstByDay' => $notdienstByDay,
            'bereitschaftByDay' => $bereitschaftByDay,
            'todayNotdienst' => $todayNotdienst,
            'todayBereitschaft' => $todayBereitschaft,
            'openIssues' => $openIssues,
            'weekOffset' => $weekOffset,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'callcenterUser' => (string) $request->session()->get('legacy_callcenter_user', ''),
            'holidayMap' => $holidayMap,
        ]);
    }

    private function throttleKey(Request $request): string {
        return 'legacy-callcenter:' . mb_strtolower((string) $request->input('username', '')) . '|' . $request->ip();
    }

    /**
     * @template TDuty of LegacyNotdienst|LegacyOnCall
     * @param Collection<int, Carbon> $days
     * @param \Illuminate\Database\Eloquent\Collection<int, TDuty> $items
     * @param array<string, string|null> $holidayMap
     * @return array<int, array<string, mixed>>
     */
    private function mapDutyByDay(Collection $days, \Illuminate\Database\Eloquent\Collection $items, Carbon $today, array $holidayMap = []): array {
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
