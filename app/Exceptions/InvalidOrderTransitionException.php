<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvalidOrderTransitionException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use App\Enums\Diary\Status;
use RuntimeException;

class InvalidOrderTransitionException extends RuntimeException {
    public static function forAction(Status $status, string $action): self {
        return new self((string) __('Die Aktion „:action“ ist im Status „:status“ nicht möglich.', [
            'action' => $action,
            'status' => $status->label(),
        ]));
    }
}
