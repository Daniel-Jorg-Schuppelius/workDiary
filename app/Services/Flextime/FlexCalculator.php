<?php

namespace App\Services\Flextime;

use App\Models\FlexBalance;
use App\Models\Holiday;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Vacation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class FlexCalculator
{
    public function __construct(protected WorkScheduleResolver $resolver) {}

    /**
     * Tagessoll in Minuten (0 wenn Feiertag, Wochenende oder Urlaub).
     */
    public function targetMinutes(User $user, CarbonInterface $day): int
    {
        if ($this->isHoliday($day) || $this->isVacation($user, $day)) {
            return 0;
        }
        $schedule = $this->resolver->for($user, $day);
        if (! $schedule->appliesOnWeekday($day->dayOfWeekIso)) {
            return 0;
        }

        return (int) $schedule->daily_target_minutes;
    }

    public function actualMinutes(User $user, CarbonInterface $day): int
    {
        return (int) TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $day->toDateString())
            ->sum('minutes');
    }

    /**
     * @return array{target:int, actual:int, balance:int}
     */
    public function dailyBalance(User $user, CarbonInterface $day): array
    {
        $target = $this->targetMinutes($user, $day);
        $actual = $this->actualMinutes($user, $day);

        return [
            'target' => $target,
            'actual' => $actual,
            'balance' => $actual - $target,
        ];
    }

    /**
     * @return array{target:int, actual:int, balance:int, days:array<string, array{target:int, actual:int, balance:int}>}
     */
    public function monthlyBalance(User $user, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        $target = 0;
        $actual = 0;
        $days = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $b = $this->dailyBalance($user, $day);
            $target += $b['target'];
            $actual += $b['actual'];
            $days[$day->toDateString()] = $b;
        }

        return [
            'target' => $target,
            'actual' => $actual,
            'balance' => $actual - $target,
            'days' => $days,
        ];
    }

    public function recompute(User $user, int $year, int $month): FlexBalance
    {
        $b = $this->monthlyBalance($user, $year, $month);

        return FlexBalance::query()->updateOrCreate(
            ['user_id' => $user->id, 'year' => $year, 'month' => $month],
            [
                'target_minutes' => $b['target'],
                'actual_minutes' => $b['actual'],
                'balance_minutes' => $b['balance'],
                'computed_at' => now(),
            ],
        );
    }

    protected function isHoliday(CarbonInterface $day): bool
    {
        $year = (int) $day->year;
        $iso = $day->toDateString();

        $dates = collect(Holiday::all())
            ->flatMap(fn (Holiday $h) => $h->is_recurring ? $h->resolveForYear($year) : [optional($h->date)->format('Y-m-d')])
            ->filter()
            ->values();

        return $dates->contains($iso);
    }

    protected function isVacation(User $user, CarbonInterface $day): bool
    {
        return Vacation::query()
            ->where('user_id', $user->id)
            ->where('status', Vacation::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $day->toDateString())
            ->whereDate('end_date', '>=', $day->toDateString())
            ->exists();
    }
}
