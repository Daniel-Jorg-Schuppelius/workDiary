<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Customer;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Stand einer Kundenfrist (Feature 197, MVP-1632): offen → erledigt,
 * verlängert oder entfällt. Eine verlängerte Frist bleibt bis zur Erledigung
 * offen (`isOpen()`); Erledigtes und Entfallenes lässt sich mit Grund wieder
 * öffnen (Fehlbedienung, Auftrag zurückgenommen). Eine erneute Verlängerung
 * ist kein Übergang (gleicher Status) und läuft im Dienst.
 */
enum CustomerDeadlineStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Completed = 'completed';
    case Extended = 'extended';
    case Waived = 'waived';

    public function label(): string {
        return (string) __('enums.customer.customer_deadline_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'info',
            self::Extended => 'warning',
            self::Completed => 'success',
            self::Waived => 'ghost',
        };
    }

    /** Noch zu erledigen (offen oder verlängert)? */
    public function isOpen(): bool {
        return $this === self::Open || $this === self::Extended;
    }

    /** @return list<string> Speicherwerte der noch zu erledigenden Fristen */
    public static function openValues(): array {
        return [self::Open->value, self::Extended->value];
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Completed, self::Extended, self::Waived],
            self::Extended => [self::Completed, self::Waived, self::Open],
            self::Completed, self::Waived => [self::Open],
        };
    }
}
