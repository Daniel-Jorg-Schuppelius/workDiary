<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FindingStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Status einer Auditfeststellung (Feature 046, Inkrement C): open →
 * inCorrection → effectivenessCheck → closed; Rücksprung
 * effectivenessCheck → inCorrection (z. B. nach unwirksamer Maßnahme —
 * der AuditService setzt dabei automatisch zurück). Der Abschluss
 * (closed) ist nur mit erledigten/wirksamen Korrekturmaßnahmen zulässig
 * ({@see \App\Services\Isms\AuditService::transitionFinding()}).
 */
enum FindingStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;

    case Open = 'open';
    case InCorrection = 'inCorrection';
    case EffectivenessCheck = 'effectivenessCheck';
    case Closed = 'closed';

    public function label(): string {
        return (string) __('enums.isms.finding-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Open => 'error',
            self::InCorrection => 'warning',
            self::EffectivenessCheck => 'info',
            self::Closed => 'success',
        };
    }

    /**
     * Erlaubte Folge-Status (State-Machine, validiert im AuditService).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::InCorrection],
            self::InCorrection => [self::EffectivenessCheck],
            self::EffectivenessCheck => [self::Closed, self::InCorrection],
            self::Closed => [],
        };
    }
}
