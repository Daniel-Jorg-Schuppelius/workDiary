<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DrivingTimeBudget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\{Organization, TravelLog, User, Vehicle};
use App\Support\Tz;
use Carbon\{CarbonImmutable, CarbonInterface};

/**
 * Vorausschau für die Disposition (Feature 144, MVP-719): wie viel Lenkzeit
 * hat ein Fahrer an einem Tag noch, wann ist die nächste Fahrtunterbrechung
 * fällig, was bleibt in Woche und Doppelwoche. Grenzwerte aus
 * {@see DrivingTimeRules}; Datenbasis dieselben Fahrten wie der Scan
 * (nur Fahrzeuge mit `subject_to_driving_time_rules`, wirksame Fahrten).
 *
 * Ergebnis je Fahrer (Minuten):
 *  array{daily_limit:int, daily_driven:int, daily_remaining:int, since_break:int,
 *        until_break:int, weekly_driven:int, weekly_remaining:int,
 *        fortnight_driven:int, fortnight_remaining:int}
 * `null`, wenn die Org die Lenkzeitregeln nicht anwendet.
 */
final class DrivingTimeBudget {
    /**
     * @return array{daily_limit:int, daily_driven:int, daily_remaining:int, since_break:int, until_break:int, weekly_driven:int, weekly_remaining:int, fortnight_driven:int, fortnight_remaining:int}|null
     */
    public function remainingFor(User $user, CarbonInterface $day, ?CarbonImmutable $now = null): ?array {
        $organization = $user->organization_id !== null ? Organization::query()->find($user->organization_id) : null;
        if (! $organization instanceof Organization) {
            return null;
        }

        return $this->remainingForUsers($organization, [(int) $user->id], $day, $now, onlyDrivers: false)[(int) $user->id] ?? null;
    }

    /**
     * Budget für mehrere Fahrer mit EINER Fahrten-Abfrage (Board/Lanes).
     * Mit `onlyDrivers` erhalten nur Personen einen Eintrag, die im Fenster
     * gefahren sind oder Standardfahrer eines geflaggten Fahrzeugs sind —
     * Büro-Personal ohne Fahrbezug bekommt kein (volles) Budget angezeigt.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{daily_limit:int, daily_driven:int, daily_remaining:int, since_break:int, until_break:int, weekly_driven:int, weekly_remaining:int, fortnight_driven:int, fortnight_remaining:int}>
     */
    public function remainingForUsers(Organization $organization, array $userIds, CarbonInterface $day, ?CarbonImmutable $now = null, bool $onlyDrivers = true): array {
        if (! $organization->drivingTimeRulesEnabled() || $userIds === []) {
            return [];
        }

        $vehicleIds = Vehicle::query()
            ->where('organization_id', $organization->getKey())
            ->subjectToDrivingTimeRules()
            ->pluck('id')
            ->map(static fn($v): int => (int) $v)
            ->all();
        if ($vehicleIds === []) {
            return [];
        }

        $tz = Tz::current();
        $dayLocal = CarbonImmutable::instance($day)->setTimezone($tz);
        $dayKey = $dayLocal->toDateString();
        $weekKey = $dayLocal->isoFormat('GGGG-[W]WW');
        $previousWeekKey = $dayLocal->subWeek()->isoFormat('GGGG-[W]WW');
        $loadFrom = $dayLocal->startOfWeek()->subWeek()->toDateString();

        /** @var array<int, list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>> $tripsByUser */
        $tripsByUser = [];
        TravelLog::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('user_id', $userIds)
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->whereBetween('date', [$loadFrom, $dayKey])
            ->effective()
            ->orderBy('started_at')
            ->get(['id', 'user_id', 'started_at', 'ended_at'])
            ->each(function (TravelLog $t) use (&$tripsByUser, $tz): void {
                if (! $t->started_at || ! $t->ended_at) {
                    return;
                }
                $tripsByUser[(int) $t->user_id][] = [
                    'started_at' => CarbonImmutable::parse($t->started_at->toIso8601String())->setTimezone($tz),
                    'ended_at' => CarbonImmutable::parse($t->ended_at->toIso8601String())->setTimezone($tz),
                ];
            });

        $driverIds = $userIds;
        if ($onlyDrivers) {
            $defaultDrivers = Vehicle::query()
                ->whereIn('id', $vehicleIds)
                ->whereNotNull('default_user_id')
                ->pluck('default_user_id')
                ->map(static fn($v): int => (int) $v)
                ->all();
            $driverIds = array_values(array_filter(
                $userIds,
                static fn(int $id): bool => isset($tripsByUser[$id]) || in_array($id, $defaultDrivers, true),
            ));
        }

        $result = [];
        foreach ($driverIds as $userId) {
            $result[$userId] = $this->budget($tripsByUser[$userId] ?? [], $dayLocal, $dayKey, $weekKey, $previousWeekKey, $now);
        }

        return $result;
    }

    /**
     * @param  list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>  $trips
     * @return array{daily_limit:int, daily_driven:int, daily_remaining:int, since_break:int, until_break:int, weekly_driven:int, weekly_remaining:int, fortnight_driven:int, fortnight_remaining:int}
     */
    private function budget(array $trips, CarbonImmutable $dayLocal, string $dayKey, string $weekKey, string $previousWeekKey, ?CarbonImmutable $now): array {
        $trips = DrivingTimeComplianceChecker::normalize($trips);
        $days = DrivingTimeComplianceChecker::aggregateDays($trips);
        $weeks = DrivingTimeComplianceChecker::groupByWeek($days);

        $thisWeek = $weeks[$weekKey]['minutes_by_date'] ?? [];
        $previousWeek = $weeks[$previousWeekKey]['minutes_by_date'] ?? [];

        // Verlängerungen (> 9 h) der Woche OHNE den Tag selbst — der Tag darf
        // eine noch freie Verlängerung nutzen.
        $extensionsUsed = 0;
        foreach ($thisWeek as $date => $minutes) {
            if ($date !== $dayKey && DrivingTimeRules::isExtendedDay($minutes)) {
                $extensionsUsed++;
            }
        }
        $dailyLimit = DrivingTimeRules::dailyLimitMinutes($extensionsUsed);
        $dailyDriven = $thisWeek[$dayKey] ?? 0;

        // Lenkzeit seit der letzten gültigen Unterbrechung (nur Fahrten des Tages);
        // liegt der Tag „heute" und die letzte Fahrt ist ≥ 45 min her, gilt die Pause als genommen.
        $todayTrips = array_values(array_filter(
            $trips,
            static fn(array $t): bool => $t['started_at']->toDateString() === $dayKey,
        ));
        $breaks = DrivingTimeRules::evaluateBreaks($todayTrips);
        $sinceBreak = $breaks['accumulated'];
        if ($todayTrips !== [] && $now !== null) {
            $lastEnd = $todayTrips[count($todayTrips) - 1]['ended_at'];
            $gap = (int) $lastEnd->diffInMinutes($now->setTimezone($dayLocal->getTimezone()), false);
            if ($gap >= DrivingTimeRules::BREAK_MINUTES || ($breaks['partial_break'] && $gap >= DrivingTimeRules::BREAK_SPLIT_SECOND_MINUTES)) {
                $sinceBreak = 0;
            }
        }

        $weeklyDriven = array_sum($thisWeek);
        $fortnightDriven = $weeklyDriven + array_sum($previousWeek);

        return [
            'daily_limit' => $dailyLimit,
            'daily_driven' => $dailyDriven,
            'daily_remaining' => max(0, $dailyLimit - $dailyDriven),
            'since_break' => $sinceBreak,
            'until_break' => max(0, DrivingTimeRules::BREAK_AFTER_DRIVING_MINUTES - $sinceBreak),
            'weekly_driven' => $weeklyDriven,
            'weekly_remaining' => max(0, DrivingTimeRules::WEEKLY_DRIVING_MINUTES - $weeklyDriven),
            'fortnight_driven' => $fortnightDriven,
            'fortnight_remaining' => max(0, DrivingTimeRules::FORTNIGHT_DRIVING_MINUTES - $fortnightDriven),
        ];
    }
}
