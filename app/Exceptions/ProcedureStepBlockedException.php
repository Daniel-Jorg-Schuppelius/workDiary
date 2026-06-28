<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureStepBlockedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use App\Models\ProcedureStepRun;
use RuntimeException;

/**
 * Signalisiert HTTP 409 mit error.code = "procedure.stepBlocked"
 * (MVP-026 §3).
 */
class ProcedureStepBlockedException extends RuntimeException {
    public const CODE = 'procedure.stepBlocked';

    public const REASON_PREVIOUS_STEP_INCOMPLETE = 'previousStepIncomplete';

    public const REASON_RUN_NOT_ACTIVE = 'runNotActive';

    public const REASON_STEP_ALREADY_FINAL = 'stepAlreadyFinal';

    public const REASON_MISSING_QUALIFICATION = 'missingQualification';

    public const REASON_MISSING_ROLE = 'missingRole';

    public const REASON_SECOND_PERSON_REQUIRED = 'secondPersonRequired';

    public const REASON_BACKUP_NOT_VERIFIED = 'backupNotVerified';

    public const REASON_PRIOR_BACKUP_MISSING = 'backupMissingOrExpired';

    /** Serverseitige Warte-/Trockenzeit noch nicht abgelaufen (MVP-064). */
    public const REASON_WAIT_NOT_ELAPSED = 'waitNotElapsed';

    public function __construct(
        public readonly string $reason,
        public readonly ?ProcedureStepRun $stepRun = null,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : sprintf('Procedure step blocked: %s', $reason));
    }

    public static function for(string $reason, ?ProcedureStepRun $stepRun = null): self {
        return new self($reason, $stepRun);
    }
}
