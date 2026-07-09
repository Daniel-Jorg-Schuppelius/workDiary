<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Cadence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

use InvalidArgumentException;

/**
 * Serialisierbare Planungsangabe eines Registry-Jobs (Feature 067,
 * MVP-175/176). Wird identisch für config-Defaults und DB-Overrides
 * verwendet und deterministisch in einen Cron-Ausdruck übersetzt —
 * dieselbe Semantik wie Laravels dailyAt()/hourly()/weeklyOn().
 */
final readonly class Cadence {
    public function __construct(
        public CadenceType $type,
        public ?string $time = null,      // "HH:MM" für dailyAt/weeklyOn/monthlyOn
        public ?int $day = null,          // Wochentag 0-6 (weeklyOn) bzw. Monatstag 1-31 (monthlyOn)
        public ?string $expression = null, // roher Cron-Ausdruck (nur Type Cron)
    ) {
        if ($type->needsTime() && !is_string($time)) {
            throw new InvalidArgumentException("Kadenz {$type->value} braucht eine Uhrzeit (HH:MM).");
        }
        if (in_array($type, [CadenceType::WeeklyOn, CadenceType::MonthlyOn], true) && $day === null) {
            throw new InvalidArgumentException("Kadenz {$type->value} braucht eine Tagesangabe.");
        }
        if ($type === CadenceType::Cron && !is_string($expression)) {
            throw new InvalidArgumentException('Kadenz cron braucht einen Ausdruck.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        return new self(
            type: CadenceType::from((string) ($data['type'] ?? '')),
            time: isset($data['time']) ? (string) $data['time'] : null,
            day: isset($data['day']) ? (int) $data['day'] : null,
            expression: isset($data['expression']) ? (string) $data['expression'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return array_filter([
            'type' => $this->type->value,
            'time' => $this->time,
            'day' => $this->day,
            'expression' => $this->expression,
        ], static fn(mixed $v): bool => $v !== null);
    }

    /**
     * Cron-Ausdruck mit identischer Semantik zu Laravels
     * Schedule-Kurzmethoden (dailyAt('03:30') === '30 3 * * *').
     */
    public function cronExpression(): string {
        [$hour, $minute] = $this->timeParts();

        return match ($this->type) {
            CadenceType::EveryMinute => '* * * * *',
            CadenceType::EveryFiveMinutes => '*/5 * * * *',
            CadenceType::EveryFifteenMinutes => '*/15 * * * *',
            CadenceType::EveryThirtyMinutes => '*/30 * * * *',
            CadenceType::Hourly => '0 * * * *',
            CadenceType::DailyAt => "{$minute} {$hour} * * *",
            CadenceType::WeeklyOn => "{$minute} {$hour} * * {$this->day}",
            CadenceType::MonthlyOn => "{$minute} {$hour} {$this->day} * *",
            CadenceType::Cron => (string) $this->expression,
        };
    }

    /** @return array{0: int, 1: int} */
    private function timeParts(): array {
        if ($this->time === null) {
            return [0, 0];
        }
        [$hour, $minute] = array_pad(explode(':', $this->time, 2), 2, '0');

        return [(int) $hour, (int) $minute];
    }
}
