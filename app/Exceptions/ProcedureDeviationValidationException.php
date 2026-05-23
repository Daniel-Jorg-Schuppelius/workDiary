<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDeviationValidationException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use RuntimeException;

class ProcedureDeviationValidationException extends RuntimeException {
    public const CODE_INVALID = 'procedure.deviationInvalid';

    public const CODE_REASON_TOO_SHORT = 'procedure.deviationReasonTooShort';

    public const CODE_CRITICAL_OPEN = 'procedure.criticalDeviationOpen';

    public const REASON_REASON_TOO_SHORT = 'reasonTooShort';

    public const REASON_INVALID_TYPE = 'invalidType';

    public const REASON_INVALID_SEVERITY = 'invalidSeverity';

    public const REASON_INVALID_ACTION = 'invalidAction';

    public const REASON_ALREADY_RECORDED = 'alreadyRecorded';

    public const REASON_CRITICAL_NEEDS_ACTION = 'criticalNeedsAction';

    public function __construct(
        public readonly string $errorCode,
        public readonly string $reason,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : sprintf('Procedure deviation invalid (%s).', $reason));
    }

    public static function reasonTooShort(): self {
        return new self(self::CODE_REASON_TOO_SHORT, self::REASON_REASON_TOO_SHORT);
    }

    public static function for(string $reason): self {
        return new self(self::CODE_INVALID, $reason);
    }

    public static function criticalOpen(): self {
        return new self(self::CODE_CRITICAL_OPEN, self::REASON_CRITICAL_NEEDS_ACTION);
    }
}
