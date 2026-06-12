<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssessmentKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer Risikobewertung (Feature 046, Inkrement D):
 * - gross  = Bruttorisiko (ohne Maßnahmen),
 * - net    = Nettorisiko (mit bestehenden Maßnahmen) — das jüngste
 *            freigegebene net-Assessment ist die maßgebliche aktuelle
 *            Bewertung des Risikos (Sync im RiskService),
 * - target = Zielrisiko (angestrebter Zustand).
 */
enum AssessmentKind: string implements HasLabel {
    use HasOptions;

    case Gross = 'gross';
    case Net = 'net';
    case Target = 'target';

    public function label(): string {
        return (string) __('enums.isms.assessment-kind.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Gross => 'error',
            self::Net => 'primary',
            self::Target => 'success',
        };
    }
}
