<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureSecondPersonException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use App\Models\ProcedureStepRun;
use RuntimeException;

/**
 * Verstoesse gegen das Vier-Augen-Prinzip (MVP-028, Issue #28).
 * `code` adressiert die Fehlerklasse fuer API-Konsumenten (HTTP 409).
 */
class ProcedureSecondPersonException extends RuntimeException {
    public const CODE_MISSING = 'procedure.secondPersonMissing';

    public const CODE_SELF_NOT_ALLOWED = 'procedure.secondPersonSelfNotAllowed';

    public const REASON_NOT_ASSIGNED = 'notAssigned';

    public const REASON_NOT_SIGNED = 'notSigned';

    public const REASON_SELF_NOT_ALLOWED = 'selfNotAllowed';

    public const REASON_ROLE_MISMATCH = 'roleMismatch';

    public const REASON_QUALIFICATION_MISSING = 'qualificationMissing';

    public const REASON_ALREADY_ASSIGNED = 'alreadyAssigned';

    public const REASON_NOT_TAKER = 'notTaker';

    public const REASON_NOT_REQUIRED = 'notRequired';

    public function __construct(
        public readonly string $errorCode,
        public readonly string $reason,
        public readonly ?ProcedureStepRun $stepRun = null,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : sprintf('Second-person violation (%s).', $reason));
    }

    public static function missing(string $reason, ?ProcedureStepRun $stepRun = null): self {
        return new self(self::CODE_MISSING, $reason, $stepRun);
    }

    public static function selfNotAllowed(?ProcedureStepRun $stepRun = null): self {
        return new self(self::CODE_SELF_NOT_ALLOWED, self::REASON_SELF_NOT_ALLOWED, $stepRun);
    }
}
