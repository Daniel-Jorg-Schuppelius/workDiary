<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityIncidentStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Lebenszyklus eines Sicherheitsvorfalls (Feature 044, MVP 2):
 * reported → triage → contained → eradicated → recovered → closed
 * (mit Rücksprüngen zur Neubewertung, siehe allowedTransitions()).
 * Der Abschluss (closed) erzwingt Ursachenanalyse + Lessons Learned
 * (Regel im SecurityIncidentService).
 */
enum SecurityIncidentStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;

    case Reported = 'reported';
    case Triage = 'triage';
    case Contained = 'contained';
    case Eradicated = 'eradicated';
    case Recovered = 'recovered';
    case Closed = 'closed';

    public function label(): string {
        return (string) __('enums.isms.security-incident-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Reported => 'error',
            self::Triage => 'warning',
            self::Contained => 'info',
            self::Eradicated => 'primary',
            self::Recovered => 'accent',
            self::Closed => 'success',
        };
    }

    public function isOpen(): bool {
        return $this !== self::Closed;
    }

    /**
     * Erlaubte Folge-Status (State-Machine, validiert im SecurityIncidentService).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Reported => [self::Triage, self::Contained, self::Closed],
            self::Triage => [self::Contained, self::Closed],
            self::Contained => [self::Eradicated, self::Triage],
            self::Eradicated => [self::Recovered, self::Contained],
            self::Recovered => [self::Closed, self::Eradicated],
            self::Closed => [self::Triage],
        };
    }
}
