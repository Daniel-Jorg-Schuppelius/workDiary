<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CadenceType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Erlaubte Planungsarten der Scheduler-Job-Registry (Feature 067,
 * MVP-175). Freie Cron-Ausdrücke (Cron) bleiben Betreiber-Funktion
 * mit Validierung (MVP-176).
 */
enum CadenceType: string implements HasLabel {
    use HasOptions;

    case EveryMinute = 'everyMinute';
    case EveryFiveMinutes = 'everyFiveMinutes';
    case EveryFifteenMinutes = 'everyFifteenMinutes';
    case EveryThirtyMinutes = 'everyThirtyMinutes';
    case Hourly = 'hourly';
    case DailyAt = 'dailyAt';
    case WeeklyOn = 'weeklyOn';
    case MonthlyOn = 'monthlyOn';
    case Cron = 'cron';

    public function label(): string {
        return __('scheduler.cadence.' . $this->value);
    }

    /** Taktlänge in Minuten; Tages- und Cron-Kadenzen zählen als 1 (kein Versatz). */
    public function intervalMinutes(): int {
        return match ($this) {
            self::EveryFiveMinutes => 5,
            self::EveryFifteenMinutes => 15,
            self::EveryThirtyMinutes => 30,
            self::Hourly => 60,
            default => 1,
        };
    }

    public function needsTime(): bool {
        return in_array($this, [self::DailyAt, self::WeeklyOn, self::MonthlyOn], true);
    }
}
