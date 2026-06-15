<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEventKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Safety;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art des Sicherheitsereignisses (Feature 013): Unfall, Beinaheunfall,
 * Gefährdung oder Mangel.
 */
enum SafetyEventKind: string implements HasLabel {
    use HasOptions;

    case Accident = 'accident';
    case NearMiss = 'nearMiss';
    case Hazard = 'hazard';
    case Defect = 'defect';

    public function label(): string {
        return (string) __('enums.safety.kind.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::Accident => 'personal_injury',
            self::NearMiss => 'warning',
            self::Hazard => 'report_problem',
            self::Defect => 'build',
        };
    }
}
