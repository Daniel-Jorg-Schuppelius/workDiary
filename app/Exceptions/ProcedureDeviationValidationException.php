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
    public const string CODE_INVALID = 'procedure.deviationInvalid';

    public const string CODE_REASON_TOO_SHORT = 'procedure.deviationReasonTooShort';

    public const string CODE_CRITICAL_OPEN = 'procedure.criticalDeviationOpen';

    public const string REASON_REASON_TOO_SHORT = 'reasonTooShort';

    public const string REASON_INVALID_TYPE = 'invalidType';

    public const string REASON_INVALID_SEVERITY = 'invalidSeverity';

    public const string REASON_INVALID_ACTION = 'invalidAction';

    public const string REASON_ALREADY_RECORDED = 'alreadyRecorded';

    public const string REASON_CRITICAL_NEEDS_ACTION = 'criticalNeedsAction';

    public const string REASON_RISK_NOT_CRITICAL = 'riskNotCritical';

    public const string REASON_RISK_ALREADY_ACCEPTED = 'riskAlreadyAccepted';

    public const string REASON_RISK_NOTE_MISSING = 'riskNoteMissing';

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
