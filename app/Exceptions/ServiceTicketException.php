<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use RuntimeException;

class ServiceTicketException extends RuntimeException {
    public const CODE_INVALID_STATUS_TRANSITION = 'serviceTicket.invalidStatusTransition';

    public const CODE_MISSING_ASSIGNEE = 'serviceTicket.missingAssignee';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public static function invalidStatusTransition(string $from, string $to): self {
        return new self(self::CODE_INVALID_STATUS_TRANSITION, "Ungültiger Ticket-Statuswechsel: {$from} -> {$to}");
    }

    public static function missingAssignee(): self {
        return new self(self::CODE_MISSING_ASSIGNEE, 'Status InProgress erfordert einen zugewiesenen Bearbeiter.');
    }
}
