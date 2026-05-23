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

enum ClassificationRequirementSeverity: string {
    case Hard = 'hard';
    case Soft = 'soft';

    public function isHard(): bool {
        return $this === self::Hard;
    }
}
