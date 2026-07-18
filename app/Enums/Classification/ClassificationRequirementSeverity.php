<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationRequirementSeverity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Classification;

use App\Enums\Contracts\HasLabel;

enum ClassificationRequirementSeverity: string implements HasLabel {
    case Hard = 'hard';
    case Soft = 'soft';

    public function label(): string {
        return (string) __('enums.classification.requirement-severity.' . $this->value);
    }

    public function isHard(): bool {
        return $this === self::Hard;
    }
}
