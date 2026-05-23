<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvalidOpenIssueTransitionException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use App\Enums\OpenIssue\OpenIssueStatus;
use RuntimeException;

class InvalidOpenIssueTransitionException extends RuntimeException {
    public static function from(OpenIssueStatus $from, OpenIssueStatus $to): self {
        return new self(sprintf(
            'Ungültiger Statuswechsel für Offenen Punkt: %s → %s.',
            $from->value,
            $to->value
        ));
    }
}
