<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Calendar\WeekViewService;
use App\Services\HolidayService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WeekController extends Controller
{
    public function __invoke(Request $request, WeekViewService $service, HolidayService $holidays): View
    {
        $anchor = $this->parseDate($request->query('date'));
        $teamScope = $request->query('scope') === 'team';
        $filterUserId = $teamScope ? (int) $request->query('user', 0) : 0;
        if ($filterUserId <= 0) {
            $filterUserId = null;
        }

        /** @var User $authUser */
        $authUser = Auth::user();
        $data = $service->build($anchor, $authUser, $teamScope, $filterUserId);

        $shiftsByDay = $service->groupByDay($data['shifts'], $data['start']);
        $assignmentsByDay = $service->groupByDay($data['assignments'], $data['start']);
        $entriesByDay = $service->groupByDay($data['entries'], $data['start']);

        $weekUsers = $teamScope ? $service->usersInWeek($anchor) : collect();

        return view('week.index', [
            'weekStart' => $data['start'],
            'weekEnd' => $data['end'],
            'days' => $data['days'],
            'shiftsByDay' => $shiftsByDay,
            'assignmentsByDay' => $assignmentsByDay,
            'entriesByDay' => $entriesByDay,
            'teamScope' => $teamScope,
            'filterUserId' => $filterUserId,
            'weekUsers' => $weekUsers,
            'service' => $service,
            'holidays' => $holidays,
            'prevDate' => $data['start']->subWeek()->toDateString(),
            'nextDate' => $data['start']->addWeek()->toDateString(),
            'todayDate' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    private function parseDate(?string $value): CarbonImmutable
    {
        if (! $value) {
            return CarbonImmutable::today();
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Exception) {
            return CarbonImmutable::today();
        }
    }
}
