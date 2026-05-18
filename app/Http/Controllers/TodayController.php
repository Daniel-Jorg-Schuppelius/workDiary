<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodayController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Attendance\AttendanceClockService;
use App\Services\Flextime\FlexCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TodayController extends Controller
{
    public function __construct(
        protected AttendanceClockService $clock,
        protected FlexCalculator $flex,
    ) {}

    public function show(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $day = $request->date('date')?->startOfDay() ?? CarbonImmutable::today();

        $attendances = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $day->toDateString())
            ->orderBy('started_at')
            ->get();

        $entries = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $day->toDateString())
            ->with(['project', 'task', 'activityCategory'])
            ->orderBy('started_at')
            ->get();

        $targetMinutes = $this->flex->targetMinutes($user, $day);
        $attendanceMinutes = (int) $attendances->sum(function (Attendance $a): int {
            if ($a->duration_minutes > 0) {
                return (int) $a->duration_minutes;
            }
            // open attendance: count from started_at to now
            if ($a->started_at) {
                $end = $a->ended_at ?? CarbonImmutable::now();
                $gross = (int) $a->started_at->diffInMinutes($end, false);

                return max(0, $gross - $a->break_minutes_total);
            }

            return 0;
        });
        $entriesMinutes = (int) $entries->sum('minutes');
        $untrackedMinutes = max(0, $attendanceMinutes - $entriesMinutes);

        // Group entries by activity for breakdown
        $byActivity = $entries->groupBy('activity_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'minutes' => (int) $group->sum('minutes'),
            ];
        });

        return view('today.show', [
            'day' => $day,
            'current' => $this->clock->current($user),
            'attendances' => $attendances,
            'entries' => $entries,
            'targetMinutes' => $targetMinutes,
            'attendanceMinutes' => $attendanceMinutes,
            'entriesMinutes' => $entriesMinutes,
            'untrackedMinutes' => $untrackedMinutes,
            'byActivity' => $byActivity,
        ]);
    }
}
