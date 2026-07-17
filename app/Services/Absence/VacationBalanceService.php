<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationBalanceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Absence;

use App\Enums\Vacation\VacationStatus;
use App\Models\{Vacation, VacationEntitlement};
use App\Services\HolidayService;
use Carbon\{Carbon, CarbonInterface};
use Illuminate\Database\Eloquent\Collection;

/**
 * Urlaubskonto-Saldo (MVP-413): Anspruch + nutzbarer Übertrag − genommene
 * und beantragte Werktage. Ohne Anspruchszeile liefert der Saldo
 * `hasEntitlement=false` — die UI zeigt dann nur genommene Tage (kein
 * erfundener Anspruch).
 */
class VacationBalanceService {
    public function __construct(private readonly HolidayService $holidayService) {}

    public function balanceFor(int $userId, int $year, ?CarbonInterface $today = null): VacationBalance {
        $today ??= Carbon::today();

        $entitlement = VacationEntitlement::query()
            ->where('user_id', $userId)
            ->where('year', $year)
            ->first();

        [$taken, $pending, $takenBeforeExpiry] = $this->countDays($userId, $year, $entitlement?->carryover_expires_on);

        $carryover = (float) ($entitlement->carryover_days ?? 0.0);
        $usableCarryover = $carryover;
        $expiresOn = $entitlement?->carryover_expires_on;
        if ($carryover > 0 && $expiresOn !== null && $today->greaterThan($expiresOn)) {
            // Nach Verfall zählt nur der bis zum Stichtag tatsächlich verbrauchte Anteil.
            $usableCarryover = min($carryover, $takenBeforeExpiry);
        }

        return new VacationBalance(
            year: $year,
            hasEntitlement: $entitlement !== null,
            entitledDays: (float) ($entitlement->entitled_days ?? 0.0),
            carryoverDays: $carryover,
            carryoverExpiresOn: $expiresOn,
            usableCarryoverDays: $usableCarryover,
            takenDays: $taken,
            pendingDays: $pending,
        );
    }

    /**
     * Salden für mehrere Nutzer in einem Rutsch (Listen/Reports).
     *
     * @param  array<int, int>  $userIds
     * @return array<int, VacationBalance>
     */
    public function balancesFor(array $userIds, int $year, ?CarbonInterface $today = null): array {
        $balances = [];
        foreach (array_unique($userIds) as $userId) {
            $balances[$userId] = $this->balanceFor($userId, $year, $today);
        }

        return $balances;
    }

    /** Anspruchsrelevante Werktage eines Zeitraums (Mo–Fr, ohne Feiertage), begrenzt auf das Jahr. */
    public function workingDaysInYear(CarbonInterface $start, CarbonInterface $end, int $year): float {
        $yearStart = Carbon::parse(sprintf('%d-01-01', $year))->startOfDay();
        $yearEnd = Carbon::parse(sprintf('%d-12-31', $year))->endOfDay();
        $from = $start->greaterThan($yearStart) ? Carbon::parse($start->toDateString()) : $yearStart->copy();
        $to = $end->lessThan($yearEnd) ? Carbon::parse($end->toDateString()) : Carbon::parse($yearEnd->toDateString());

        return (float) $this->countWorkdays($from, $to);
    }

    /**
     * @return array{0: float, 1: float, 2: float} [genommen, beantragt, genommen bis Verfallsdatum]
     */
    private function countDays(int $userId, int $year, ?CarbonInterface $carryoverExpiresOn): array {
        $yearStart = Carbon::parse(sprintf('%d-01-01', $year))->startOfDay();
        $yearEnd = Carbon::parse(sprintf('%d-12-31', $year))->endOfDay();

        /** @var Collection<int, Vacation> $vacations */
        $vacations = Vacation::query()
            ->where('user_id', $userId)
            ->whereIn('status', [VacationStatus::Approved, VacationStatus::Pending])
            ->scopes(['overlapping' => [$yearStart, $yearEnd]])
            ->get();

        $taken = 0.0;
        $pending = 0.0;
        $takenBeforeExpiry = 0.0;

        foreach ($vacations as $vacation) {
            if (! $vacation->type->countsAgainstEntitlement()) {
                continue;
            }
            $days = $this->workingDaysInYear($vacation->start_date, $vacation->end_date, $year);
            if ($days <= 0) {
                continue;
            }
            if ($vacation->status === VacationStatus::Pending) {
                $pending += $days;

                continue;
            }
            $taken += $days;
            if ($carryoverExpiresOn !== null && $vacation->start_date->lessThanOrEqualTo($carryoverExpiresOn)) {
                $end = $vacation->end_date->lessThan($carryoverExpiresOn) ? $vacation->end_date : $carryoverExpiresOn;
                $takenBeforeExpiry += $this->workingDaysInYear($vacation->start_date, $end, $year);
            }
        }

        return [$taken, $pending, $takenBeforeExpiry];
    }

    private function countWorkdays(CarbonInterface $start, CarbonInterface $end): int {
        if ($start->greaterThan($end)) {
            return 0;
        }
        $count = 0;
        $cursor = Carbon::parse($start->toDateString());
        $endDay = Carbon::parse($end->toDateString());
        while ($cursor->lte($endDay)) {
            if ($cursor->isWeekday() && ! $this->holidayService->isHoliday($cursor)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }
}
