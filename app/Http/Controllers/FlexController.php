<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\User;
use App\Services\Calendar\WeekViewService;
use App\Services\Flextime\FlexCalculator;
use App\Services\UI\DateRangeContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FlexController extends Controller
{
    use ResolvesGlobalDateRange;

    public function __construct(protected FlexCalculator $calc, protected WeekViewService $weekService) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->migrateLegacyYearMonth($request, 'flex.index')) {
            return $redirect;
        }

        /** @var User $authUser */
        $authUser = Auth::user();
        $isAdmin = $authUser->isAdmin();

        // Admins dürfen via ?user=… die Gleitzeit eines anderen Users sehen.
        $targetId = (int) ($request->input('user') ?? $authUser->id);
        $user = ($isAdmin && $targetId !== (int) $authUser->id)
            ? User::findOrFail($targetId)
            : $authUser;

        $range = $this->globalDateRange();

        // Liste aller Monate im gewählten Zeitraum (chronologisch).
        $months = [];
        $cursor = $range['from']->startOfMonth();
        $end = $range['to']->startOfMonth();
        while ($cursor <= $end) {
            $months[] = [
                'key' => $cursor->format('Y-m'),
                'year' => $cursor->year,
                'month' => $cursor->month,
                'label' => $cursor->translatedFormat('F Y'),
                'shortLabel' => $cursor->translatedFormat('M Y'),
            ];
            $cursor = $cursor->addMonth();
        }
        if ($months === []) {
            $now = CarbonImmutable::now();
            $months[] = [
                'key' => $now->format('Y-m'),
                'year' => $now->year,
                'month' => $now->month,
                'label' => $now->translatedFormat('F Y'),
                'shortLabel' => $now->translatedFormat('M Y'),
            ];
        }

        // Aktiven Monat aus ?activeMonth=YYYY-MM ableiten, sonst erster im Bereich.
        $activeKey = (string) $request->input('activeMonth', $months[0]['key']);
        $active = collect($months)->firstWhere('key', $activeKey) ?? $months[0];
        $activeKey = $active['key'];
        $year = $active['year'];
        $month = $active['month'];

        $users = $isAdmin
            ? User::query()->orderBy('name')->get()
            : collect();

        return view('flex.index', [
            'user' => $user,
            'authUser' => $authUser,
            'users' => $users,
            'year' => $year,
            'month' => $month,
            'months' => $months,
            'activeKey' => $activeKey,
            'summary' => $this->calc->monthlyBalance($user, $year, $month),
            'isAdmin' => $isAdmin,
            'service' => $this->weekService,
        ]);
    }

    public function admin(Request $request): RedirectResponse
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        abort_unless((bool) $authUser?->isAdmin(), 403);

        // Admin-Ansicht ist jetzt in index() integriert (User-Tabs).
        return redirect()->route('flex.index', $request->only(['user']));
    }

    /**
     * Backward-Compat: bestehende ?year=&month= Links in den globalen
     * Zeitraum (custom, voller Monat) übersetzen.
     */
    private function migrateLegacyYearMonth(Request $request, string $routeName): ?RedirectResponse
    {
        if (! $request->filled('year') && ! $request->filled('month')) {
            return null;
        }

        $now = CarbonImmutable::now();
        $year = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);
        $month = max(1, min(12, $month));

        $start = (CarbonImmutable::create($year, $month, 1) ?: CarbonImmutable::now())->startOfMonth();
        $end = $start->endOfMonth();

        app(DateRangeContext::class)->set(
            DateRangeContext::PRESET_CUSTOM,
            $start->toDateString(),
            $end->toDateString(),
        );

        return redirect()->route($routeName, $request->except(['year', 'month']));
    }
}
