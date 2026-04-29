<?php

namespace App\Http\Controllers;

use App\Models\Legacy\LegacyDiaryEntry;
use App\Models\Legacy\LegacyNotdienst;
use App\Models\Legacy\LegacyOnCall;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LegacyCallcenterController extends Controller {
    public function showLoginForm(): View {
        return view('legacy.callcenter.login');
    }

    public function login(Request $request): RedirectResponse {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (! filled(config('database.connections.legacy.database'))) {
            return back()->withErrors([
                'username' => __('Legacy-Datenbank ist nicht konfiguriert.'),
            ])->onlyInput('username');
        }

        try {
            $callUser = DB::connection('legacy')
                ->table('calluser')
                ->where('uname', $credentials['username'])
                ->where('userpw', $credentials['password'])
                ->first();
        } catch (\Throwable) {
            $callUser = null;
        }

        if (! $callUser) {
            return back()->withErrors([
                'username' => __('Nutzername oder Passwort ist falsch.'),
            ])->onlyInput('username');
        }

        $request->session()->put('legacy_callcenter_user', (string) $callUser->uname);

        return redirect()->route('legacy.callcenter.notdienst');
    }

    public function logout(Request $request): RedirectResponse {
        $request->session()->forget('legacy_callcenter_user');

        return redirect()->route('legacy.callcenter.login');
    }

    public function notdienstPlan(Request $request): View {
        $weekOffset = (int) $request->query('week', 0);
        $today = Carbon::today();
        $start = $today->copy()->subDay()->addWeeks($weekOffset);
        $days = collect(range(0, 6))->map(fn(int $offset) => $start->copy()->addDays($offset));

        $rangeStart = $start->copy()->toDateString();
        $rangeEnd = $start->copy()->addDays(6)->toDateString();

        $notdienstByDay = [];
        $bereitschaftByDay = [];
        foreach ($days as $day) {
            $dateStr = $day->toDateString();

            $nd = LegacyNotdienst::query()
                ->with('mitarbeiter:id,uname,email')
                ->whereDate('von', '<=', $dateStr)
                ->whereDate('bis', '>=', $dateStr)
                ->first();

            $br = LegacyOnCall::query()
                ->with('mitarbeiter:id,uname,email')
                ->whereDate('von', '<=', $dateStr)
                ->whereDate('bis', '>=', $dateStr)
                ->first();

            $notdienstByDay[] = [
                'date' => $day,
                'isToday' => $day->isSameDay($today),
                'user' => optional($nd?->mitarbeiter)->uname,
                'email' => optional($nd?->mitarbeiter)->email,
                'von' => $nd?->von,
                'bis' => $nd?->bis,
            ];

            $bereitschaftByDay[] = [
                'date' => $day,
                'isToday' => $day->isSameDay($today),
                'user' => optional($br?->mitarbeiter)->uname,
                'email' => optional($br?->mitarbeiter)->email,
                'von' => $br?->von,
                'bis' => $br?->bis,
            ];
        }

        $todayNotdienst = collect($notdienstByDay)->firstWhere('isToday', true);
        $todayBereitschaft = collect($bereitschaftByDay)->firstWhere('isToday', true);

        $openIssues = LegacyDiaryEntry::query()
            ->with('mitarbeiter:id,uname')
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
            'rangeStart' => Carbon::parse($rangeStart),
            'rangeEnd' => Carbon::parse($rangeEnd),
            'callcenterUser' => (string) $request->session()->get('legacy_callcenter_user', ''),
        ]);
    }
}
