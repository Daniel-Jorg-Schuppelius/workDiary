<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEventStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Safety;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Statusmaschine eines Sicherheitsereignisses (Feature 013):
 * gemeldet → in Untersuchung → Maßnahmen definiert → geschlossen.
 * Der Abschluss erfordert eine Ursachenanalyse (root_cause).
 */
enum SafetyEventStatus: string implements HasLabel {
    use HasOptions;

    case Reported = 'reported';
    case Investigating = 'investigating';
    case MeasuresDefined = 'measuresDefined';
    case Closed = 'closed';

    public function label(): string {
        return (string) __('enums.safety.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Reported => 'warning',
            self::Investigating => 'info',
            self::MeasuresDefined => 'primary',
            self::Closed => 'success',
        };
    }

    public function isClosed(): bool {
        return $this === self::Closed;
    }

    /**
     * Erlaubte Folgezustände der Statusmaschine.
     *
     * @return list<SafetyEventStatus>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Reported => [self::Investigating, self::Closed],
            self::Investigating => [self::MeasuresDefined, self::Closed],
            self::MeasuresDefined => [self::Closed],
            self::Closed => [self::Investigating],
        };
    }
}
