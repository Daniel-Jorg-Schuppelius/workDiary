<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvalidProtocolTransitionException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use App\Enums\Protocol\ProtocolStatus;
use RuntimeException;

class InvalidProtocolTransitionException extends RuntimeException {
    public static function from(ProtocolStatus $from, string $action): self {
        return new self(sprintf(
            'Ungültiger Statuswechsel für Protokoll: %s → %s.',
            $from->value,
            $action
        ));
    }

    public static function immutable(ProtocolStatus $status): self {
        return new self(sprintf(
            'Protokoll im Status „%s" ist unveränderlich.',
            $status->value
        ));
    }
}
