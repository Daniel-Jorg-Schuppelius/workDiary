<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CorrectiveActionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Status einer Korrekturmaßnahme (Feature 046, Inkrement C): open →
 * inProgress → done (umgesetzt, setzt completed_on) → effective|ineffective
 * (Wirksamkeitsprüfung, Pflicht-Notiz effectiveness_note — AuditService).
 * ineffective → inProgress erlaubt Nachbesserung; gleichzeitig setzt der
 * Service die zugehörige Feststellung zurück auf inCorrection.
 */
enum CorrectiveActionStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;

    case Open = 'open';
    case InProgress = 'inProgress';
    case Done = 'done';
    case Effective = 'effective';
    case Ineffective = 'ineffective';

    public function label(): string {
        return (string) __('enums.isms.corrective-action-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Open => 'ghost',
            self::InProgress => 'info',
            self::Done => 'primary',
            self::Effective => 'success',
            self::Ineffective => 'error',
        };
    }

    /**
     * Erlaubte Folge-Status (State-Machine, validiert im AuditService):
     * open → inProgress|done (kleine Sofortmaßnahmen dürfen direkt auf
     * done); done → effective|ineffective (Wirksamkeitsprüfung) oder
     * zurück auf inProgress (versehentlich erledigt); ineffective →
     * inProgress (Nachbesserung); effective ist final.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::InProgress, self::Done],
            self::InProgress => [self::Done],
            self::Done => [self::Effective, self::Ineffective, self::InProgress],
            self::Ineffective => [self::InProgress],
            self::Effective => [],
        };
    }

    /** Zählt die Maßnahme als erledigt (umgesetzt oder wirksam geprüft)? */
    public function isCompleted(): bool {
        return in_array($this, [self::Done, self::Effective], true);
    }

    /** Offen im Sinne des Fristen-Scanners (überfällig meldbar)? */
    public function isPending(): bool {
        return in_array($this, [self::Open, self::InProgress], true);
    }
}
