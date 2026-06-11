<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RiskStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus eines ISMS-Risikos:
 * identified → analyzed → treated → accepted → closed
 * (mit Rücksprüngen zur Neubewertung, siehe allowedTransitions()).
 */
enum RiskStatus: string implements HasLabel {
    use HasOptions;

    case Identified = 'identified';
    case Analyzed = 'analyzed';
    case Treated = 'treated';
    case Accepted = 'accepted';
    case Closed = 'closed';

    public function label(): string {
        return (string) __('enums.isms.risk-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Identified => 'ghost',
            self::Analyzed => 'info',
            self::Treated => 'primary',
            self::Accepted => 'warning',
            self::Closed => 'success',
        };
    }

    public function isOpen(): bool {
        return $this !== self::Closed;
    }

    /**
     * Erlaubte Folge-Status (State-Machine, validiert im RiskService).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Identified => [self::Analyzed, self::Closed],
            self::Analyzed => [self::Treated, self::Accepted, self::Closed],
            self::Treated => [self::Analyzed, self::Accepted, self::Closed],
            self::Accepted => [self::Analyzed, self::Closed],
            self::Closed => [self::Analyzed],
        };
    }
}
