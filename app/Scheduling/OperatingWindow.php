<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperatingWindow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

use App\Support\Setting;

/**
 * Betriebsfenster des Servers (Feature 067): Eine Installation, die nachts
 * abschaltet, verpasst jeden Tageszeit-Job außerhalb des Fensters — der
 * Wächter meldet ihn jeden Morgen als überfällig. Solche Zeiten rücken an
 * den Fensterbeginn: nie früher als geplant, Reihenfolge bleibt erhalten,
 * die Ruhezeit wird auf höchstens SPREAD_MINUTES gestaucht.
 *
 * Ohne Einstellung (oder Beginn = Ende) läuft der Server rund um die Uhr,
 * Kadenzen bleiben unverändert. Uhrzeiten gelten in app.schedule_timezone.
 */
final readonly class OperatingWindow {
    private const DAY = 1440;

    /** Zeitraum ab Fensterbeginn, in den die Ruhezeit gestaucht wird. */
    private const SPREAD_MINUTES = 120;

    private function __construct(
        public int $start, // Minute des Tages, ab der der Server läuft
        public int $end,   // Minute des Tages, ab der er ruht (0 = Mitternacht)
    ) {}

    public static function fromSettings(): ?self {
        return self::fromTimes(
            Setting::get('scheduler.operating_window_start'),
            Setting::get('scheduler.operating_window_end'),
        );
    }

    public static function fromTimes(mixed $start, mixed $end): ?self {
        $startMinute = self::minuteOfDay($start);
        $endMinute = self::minuteOfDay($end);
        if ($startMinute === null || $endMinute === null || $startMinute === $endMinute) {
            return null;
        }

        return new self($startMinute, $endMinute);
    }

    /** „08:00–24:00" — Ende Mitternacht als 24:00, damit das Fenster lesbar bleibt. */
    public function label(): string {
        return self::format($this->start) . '–' . ($this->end === 0 ? '24:00' : self::format($this->end));
    }

    /** Kadenz mit Uhrzeiten außerhalb des Fensters an den Fensterbeginn verschoben. */
    public function apply(Cadence $cadence): Cadence {
        return match ($cadence->type) {
            CadenceType::DailyAt, CadenceType::WeeklyOn, CadenceType::MonthlyOn => $this->applyToTime($cadence),
            CadenceType::Cron => $this->applyToCron($cadence),
            default => $cadence, // Minuten-/Stundentakte laufen im Fenster ohnehin weiter
        };
    }

    private function applyToTime(Cadence $cadence): Cadence {
        $minute = self::minuteOfDay($cadence->time);
        $shift = $minute === null ? null : $this->shift($minute);
        if ($shift === null) {
            return $cadence;
        }

        [$target, $nextDay] = $shift;
        $day = $cadence->day;
        if ($nextDay && $day !== null) {
            $day = $cadence->type === CadenceType::WeeklyOn ? ($day + 1) % 7 : ($day >= 31 ? 1 : $day + 1);
        }

        return new Cadence($cadence->type, self::format($target), $day);
    }

    /**
     * Nur Ausdrücke mit fester Minute und Stunde; über Mitternacht zusätzlich
     * nur, wenn sich Monats- und Wochentag eindeutig weiterzählen lassen —
     * sonst bleibt der Ausdruck, wie er ist.
     */
    private function applyToCron(Cadence $cadence): Cadence {
        $fields = preg_split('/\s+/', trim((string) $cadence->expression)) ?: [];
        if (count($fields) !== 5 || ! ctype_digit($fields[0]) || ! ctype_digit($fields[1])) {
            return $cadence;
        }
        $shift = $this->shift((int) $fields[1] * 60 + (int) $fields[0]);
        if ($shift === null) {
            return $cadence;
        }

        [$target, $nextDay] = $shift;
        if ($nextDay) {
            $dayOfMonth = self::nextDayField($fields[2], static fn(int $d): int => $d >= 31 ? 1 : $d + 1);
            $dayOfWeek = self::nextDayField($fields[4], static fn(int $d): int => ($d % 7 + 1) % 7);
            if ($dayOfMonth === null || $dayOfWeek === null || $fields[3] !== '*') {
                return $cadence;
            }
            [$fields[2], $fields[4]] = [$dayOfMonth, $dayOfWeek];
        }
        [$fields[0], $fields[1]] = [(string) ($target % 60), (string) intdiv($target, 60)];

        return new Cadence(CadenceType::Cron, expression: implode(' ', $fields));
    }

    /**
     * Zielminute + Tageswechsel für eine Uhrzeit in der Ruhezeit, null im Fenster.
     *
     * @return array{0: int, 1: bool}|null
     */
    private function shift(int $minute): ?array {
        $closed = ($this->start - $this->end + self::DAY) % self::DAY;
        $sinceClose = ($minute - $this->end + self::DAY) % self::DAY;
        if ($sinceClose >= $closed) {
            return null;
        }

        $target = ($this->start + intdiv($sinceClose * min($closed, self::SPREAD_MINUTES), $closed)) % self::DAY;

        return [$target, $target < $minute];
    }

    /** @param \Closure(int): int $next */
    private static function nextDayField(string $field, \Closure $next): ?string {
        if ($field === '*') {
            return '*';
        }

        return ctype_digit($field) ? (string) $next((int) $field) : null;
    }

    private static function minuteOfDay(mixed $time): ?int {
        if (! is_string($time) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m) !== 1) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    private static function format(int $minute): string {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
