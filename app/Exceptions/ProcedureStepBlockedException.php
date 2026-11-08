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

use App\Models\Procedure\ProcedureStepRun;
use RuntimeException;

/**
 * Signalisiert HTTP 409 mit error.code = "procedure.stepBlocked"
 * (MVP-026 §3).
 */
class ProcedureStepBlockedException extends RuntimeException {
    public const string CODE = 'procedure.stepBlocked';

    public const string REASON_PREVIOUS_STEP_INCOMPLETE = 'previousStepIncomplete';

    public const string REASON_RUN_NOT_ACTIVE = 'runNotActive';

    public const string REASON_STEP_ALREADY_FINAL = 'stepAlreadyFinal';

    public const string REASON_MISSING_QUALIFICATION = 'missingQualification';

    public const string REASON_MISSING_ROLE = 'missingRole';

    public const string REASON_SECOND_PERSON_REQUIRED = 'secondPersonRequired';

    public const string REASON_BACKUP_NOT_VERIFIED = 'backupNotVerified';

    public const string REASON_PRIOR_BACKUP_MISSING = 'backupMissingOrExpired';

    /** Serverseitige Warte-/Trockenzeit noch nicht abgelaufen (MVP-064). */
    public const string REASON_WAIT_NOT_ELAPSED = 'waitNotElapsed';

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
