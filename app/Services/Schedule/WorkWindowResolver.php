<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkWindowResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\Calendar\AvailabilityWindow;
use App\Models\Platform\User;
use App\Models\Schedule\ScheduledShift;
use Carbon\CarbonImmutable;

/**
 * Arbeitsfenster eines Mitarbeiters an einem Tag (Ortszeit `HH:MM`):
 * Schichten, sonst Verfügbarkeiten, sonst der Standard-Arbeitstag. Gemeinsame
 * Quelle für Lückenfüller und Tourvorschlag (MVP-1175).
 */
class WorkWindowResolver {
    public const DEFAULT_DAY_START = '08:00';

    public const DEFAULT_DAY_END = '17:00';

    /** @return list<array{0: string, 1: string}> */
    public function windowsFor(User $user, CarbonImmutable $date): array {
        $windows = [];
        $shifts = ScheduledShift::query()->forUser((int) $user->id)->forDate($date)->get();
        foreach ($shifts as $shift) {
            $start = $shift->resolvedStartTime();
            $end = $shift->resolvedEndTime();
            if ($start !== null && $end !== null) {
                $windows[] = [substr($start, 0, 5), substr($end, 0, 5)];
            }
        }
        if ($windows === []) {
            $availability = AvailabilityWindow::query()
                ->forUser((int) $user->id)
                ->forDate($date)
                ->get()
                ->filter(fn(AvailabilityWindow $window): bool => $window->kind->value !== 'unavailable');
            foreach ($availability as $window) {
                $windows[] = [
                    $window->start_time !== null ? substr((string) $window->start_time, 0, 5) : self::DEFAULT_DAY_START,
                    $window->end_time !== null ? substr((string) $window->end_time, 0, 5) : self::DEFAULT_DAY_END,
                ];
            }
        }
        if ($windows === []) {
            $windows[] = [self::DEFAULT_DAY_START, self::DEFAULT_DAY_END];
        }

        return $windows;
    }

    /**
     * Frühester Beginn und spätestes Ende des Tages als Sekunden seit Mitternacht.
     *
     * @return array{0: int, 1: int}
     */
    public function spanFor(User $user, CarbonImmutable $date): array {
        $start = PHP_INT_MAX;
        $end = 0;
        foreach ($this->windowsFor($user, $date) as [$from, $to]) {
            $start = min($start, self::seconds($from));
            $end = max($end, self::seconds($to));
        }

        return [$start, $end];
    }

    public static function seconds(string $clock): int {
        [$h, $m] = array_map('intval', explode(':', substr($clock, 0, 5)));

        return $h * 3600 + $m * 60;
    }
}
