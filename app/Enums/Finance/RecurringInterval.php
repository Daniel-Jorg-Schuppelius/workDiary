<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringInterval.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use Carbon\CarbonImmutable;

/**
 * Wiederholungsrhythmus (Feature 125, MVP-675).
 */
enum RecurringInterval: string implements HasLabel {
    use HasOptions;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnually = 'semi_annually';
    case Annually = 'annually';

    public function label(): string {
        return (string) __('enums.finance.recurring-interval.' . $this->value);
    }

    public function tone(): string {
        return 'ghost';
    }

    public function months(): int {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::SemiAnnually => 6,
            self::Annually => 12,
        };
    }

    public function next(CarbonImmutable $from): CarbonImmutable {
        return $from->addMonthsNoOverflow($this->months());
    }

    /**
     * Periodenschlüssel der Fälligkeit — Grundlage der Idempotenz. Beim
     * Jahresrhythmus reicht das Jahr, sonst der Monat: Ein feinerer Schlüssel
     * würde bei verschobenen Läufen zwei Vorgänge für dieselbe Periode
     * erlauben.
     */
    public function periodKey(CarbonImmutable $due): string {
        return $this === self::Annually
            ? $due->format('Y')
            : $due->format('Y-m');
    }
}
