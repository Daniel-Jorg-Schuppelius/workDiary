<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Period;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer Periode (Feature 198/201): Jahr, Halbjahr, Quartal, Monat. Der
 * Schlüssel einer Periode steht in {@see \App\Support\Period\PeriodKey}.
 */
enum PeriodKind: string implements HasLabel {
    use HasOptions;

    case Year = 'year';
    case HalfYear = 'half_year';
    case Quarter = 'quarter';
    case Month = 'month';

    public function label(): string {
        return (string) __('enums.period.period_kind.' . $this->value);
    }

    /** Länge einer Periode in Monaten. */
    public function months(): int {
        return match ($this) {
            self::Year => 12,
            self::HalfYear => 6,
            self::Quarter => 3,
            self::Month => 1,
        };
    }

    /** Perioden je Kalenderjahr — zugleich der höchste Ordnungswert. */
    public function perYear(): int {
        return intdiv(12, $this->months());
    }

    /**
     * Gröbste Art, die in einen Wiederholungsabstand von $months Monaten passt
     * (Feature 201, MVP-1646): 12 und mehr → Jahr, 6–11 → Halbjahr, 3–5 →
     * Quartal, sonst Monat. So fällt jedes Vorkommen einer Reihe in eine eigene
     * Periode, und „ab der nächsten Periode“ trifft das nächste Vorkommen.
     */
    public static function forMonths(int $months): self {
        return match (true) {
            $months >= 12 => self::Year,
            $months >= 6 => self::HalfYear,
            $months >= 3 => self::Quarter,
            default => self::Month,
        };
    }
}
