<?php

namespace App\Services\Flextime;

use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonInterface;

class WorkScheduleResolver
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'weekly_minutes' => (int) config('timesheet.defaults.weekly_minutes', 2400),
            'daily_target_minutes' => (int) config('timesheet.defaults.daily_target_minutes', 480),
            'working_days' => (array) config('timesheet.defaults.working_days', [1, 2, 3, 4, 5]),
            'core_start' => config('timesheet.defaults.core_start', '09:00'),
            'core_end' => config('timesheet.defaults.core_end', '15:00'),
            'frame_start' => config('timesheet.defaults.frame_start', '06:00'),
            'frame_end' => config('timesheet.defaults.frame_end', '20:00'),
            'break_after_minutes' => (int) config('timesheet.defaults.break_after_minutes', 360),
            'break_minutes' => (int) config('timesheet.defaults.break_minutes', 30),
        ];
    }

    public function for(User $user, CarbonInterface $on): WorkSchedule
    {
        $schedule = $user->workSchedule($on);
        if ($schedule) {
            return $schedule;
        }

        $defaults = self::defaults();
        $schedule = new WorkSchedule($defaults);
        $schedule->user_id = $user->id;
        $schedule->valid_from = $on->copy()->startOfMonth();
        $schedule->exists = false;

        return $schedule;
    }
}
