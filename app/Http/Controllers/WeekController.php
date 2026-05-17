<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeekController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\User;
use App\Services\Calendar\WeekViewService;
use App\Services\HolidayService;
use App\Services\UI\DateRangeContext;
use App\Support\WeekDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WeekController extends Controller
{
    use ResolvesGlobalDateRange;

    /** Maximalanzahl gleichzeitig gerenderter Wochen-Tabs. */
    private const MAX_WEEKS = 12;

    public function __invoke(Request $request, WeekViewService $service, HolidayService $holidays): View|RedirectResponse
    {
        // Backward-Compat: alte Bookmarks/Links mit ?date=YYYY-MM-DD setzen einmalig
        // den globalen Range auf die entsprechende Woche und leiten dann auf die
        // saubere Wochen-URL um. Der globale Header-Selektor übernimmt danach.
        if ($request->filled('date')) {
            $date = $this->parseDate((string) $request->query('date'));
            $weekStart = $date->startOfWeek(WeekDay::MONDAY);
            $weekEnd = $weekStart->endOfWeek(WeekDay::SUNDAY);
            app(DateRangeContext::class)->set(
                DateRangeContext::PRESET_CUSTOM,
                $weekStart->toDateString(),
                $weekEnd->toDateString(),
            );

            return redirect()->route('week.index', $request->except('date'));
        }

        $teamScope = $request->query('scope') === 'team';
        $filterUserId = $teamScope ? (int) $request->query('user', 0) : 0;
        if ($filterUserId <= 0) {
            $filterUserId = null;
        }

        /** @var User $authUser */
        $authUser = Auth::user();

        $range = $this->globalDateRange();
        $weeks = $this->collectWeekStarts($range['from'], $range['to']);

        $totalWeeks = count($weeks);
        $weeksTruncated = $totalWeeks > self::MAX_WEEKS;
        if ($weeksTruncated) {
            $weeks = array_slice($weeks, 0, self::MAX_WEEKS);
        }

        $today = CarbonImmutable::today();
        [$weekViews, $activeKey] = $this->buildWeekViews($weeks, $service, $authUser, $teamScope, $filterUserId, $today);

        // User-Tabs in Team-Sicht: Vereinigung aller Benutzer, die in den
        // geladenen Wochen Einträge / Shifts / Notdienste haben. Bewusst
        // unabhängig vom aktuell gewählten User-Filter, damit nach Auswahl
        // eines Users die anderen Tabs erhalten bleiben.
        $weekUsers = $this->collectWeekUsers($weekViews, $service, $teamScope);

        return view('week.index', [
            'weekViews' => $weekViews,
            'activeKey' => $activeKey,
            'totalWeeks' => $totalWeeks,
            'weeksTruncated' => $weeksTruncated,
            'rangeFrom' => $range['from'],
            'rangeTo' => $range['to'],
            'teamScope' => $teamScope,
            'filterUserId' => $filterUserId,
            'weekUsers' => $weekUsers,
            'service' => $service,
            'holidays' => $holidays,
            'todayDate' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    /**
     * @param  array<int, CarbonImmutable>  $weeks
     * @return array{0: array<int, array<string, mixed>>, 1: ?string}
     */
    private function buildWeekViews(array $weeks, WeekViewService $service, User $authUser, bool $teamScope, ?int $filterUserId, CarbonImmutable $today): array
    {
        $weekViews = [];
        $activeKey = null;

        foreach ($weeks as $weekStart) {
            $data = $service->build($weekStart, $authUser, $teamScope, $filterUserId);
            $key = sprintf('kw-%d-%d', $weekStart->isoWeek, $weekStart->isoWeekYear);
            $weekEndDay = $data['start']->addDays(6);

            $weekViews[] = [
                'key' => $key,
                'isoWeek' => $weekStart->isoWeek,
                'isoYear' => $weekStart->isoWeekYear,
                'start' => $data['start'],
                'end' => $data['end'],
                'days' => $data['days'],
                'shiftsByDay' => $service->groupByDay($data['shifts'], $data['start']),
                'assignmentsByDay' => $service->groupByDay($data['assignments'], $data['start']),
                'entriesByDay' => $service->groupByDay($data['entries'], $data['start']),
                'rangeLabel' => $data['start']->isoFormat('DD.MM.').' – '.$weekEndDay->isoFormat('DD.MM.YYYY'),
                'shortLabel' => $data['start']->isoFormat('DD.MM.').'–'.$weekEndDay->isoFormat('DD.MM.'),
            ];

            if ($activeKey === null && $today->betweenIncluded($data['start'], $weekEndDay)) {
                $activeKey = $key;
            }
        }

        if ($activeKey === null && $weekViews !== []) {
            $activeKey = $weekViews[0]['key'];
        }

        return [$weekViews, $activeKey];
    }

    /**
     * @param  array<int, array<string, mixed>>  $weekViews
     * @return Collection<int, mixed>
     */
    private function collectWeekUsers(array $weekViews, WeekViewService $service, bool $teamScope): Collection
    {
        if (! $teamScope) {
            return collect();
        }

        $collected = collect();
        foreach ($weekViews as $wv) {
            $collected = $collected->merge($service->usersInWeek($wv['start']));
        }

        return $collected->unique('id')->sortBy('name')->values();
    }

    /**
     * Liefert die Montag-Starts aller ISO-Wochen, die den Range [from, to] überlappen.
     *
     * @return array<int, CarbonImmutable>
     */
    private function collectWeekStarts(CarbonInterface $from, CarbonInterface $to): array
    {
        $cursor = CarbonImmutable::instance($from)->startOfWeek(WeekDay::MONDAY)->startOfDay();
        $end = CarbonImmutable::instance($to)->endOfDay();

        if ($end->lessThan($cursor)) {
            return [CarbonImmutable::today()->startOfWeek(WeekDay::MONDAY)->startOfDay()];
        }

        $weeks = [];
        // Sicherheitsobergrenze gegen pathologische Ranges
        for ($i = 0; $i < 260 && $cursor->lessThanOrEqualTo($end); $i++) {
            $weeks[] = $cursor;
            $cursor = $cursor->addWeek();
        }

        return $weeks !== []
            ? $weeks
            : [CarbonImmutable::today()->startOfWeek(WeekDay::MONDAY)->startOfDay()];
    }

    private function parseDate(string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value);
        } catch (\Exception) {
            return CarbonImmutable::today();
        }
    }
}
