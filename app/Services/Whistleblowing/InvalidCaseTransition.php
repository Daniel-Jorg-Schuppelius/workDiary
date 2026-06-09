<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvalidCaseTransition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Enums\Whistleblowing\CaseStatus;
use RuntimeException;

class InvalidCaseTransition extends RuntimeException {
    public static function between(CaseStatus $from, CaseStatus $to): self {
        return new self(sprintf('Unzulaessiger Statuswechsel: %s → %s.', $from->value, $to->value));
    }
}
