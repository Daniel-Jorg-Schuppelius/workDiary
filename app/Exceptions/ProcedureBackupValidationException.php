<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureBackupValidationException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use RuntimeException;

/**
 * Wird geworfen, wenn ein Backup-Nachweis die Vorgaben aus
 * `step_def.config` verletzt (Mindestgroesse, Hoechstalter,
 * Checksum-Mismatch, fehlende URI bei storage=external).
 */
class ProcedureBackupValidationException extends RuntimeException {
    public const CODE = 'procedure.backupInvalid';

    public const REASON_TOO_SMALL = 'tooSmall';

    public const REASON_TOO_OLD = 'tooOld';

    public const REASON_CHECKSUM_MISMATCH = 'checksumMismatch';

    public const REASON_MISSING_EXTERNAL_REF = 'missingExternalRef';

    public const REASON_MISSING_ATTACHMENT = 'missingAttachment';

    public const REASON_NOT_VERIFIED = 'notVerified';

    public function __construct(public readonly string $reason, string $message = '') {
        parent::__construct($message !== '' ? $message : sprintf('Backup proof invalid: %s', $reason));
    }

    public static function for(string $reason): self {
        return new self($reason);
    }
}
