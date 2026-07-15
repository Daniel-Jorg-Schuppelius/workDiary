<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupCommitInvalidException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup\Exceptions;

use RuntimeException;

/**
 * Commit-Manifest fehlt, ist unlesbar oder die crypto_sign-Signatur ist
 * gebrochen — die Generation gilt als NICHT restorable.
 */
class BackupCommitInvalidException extends RuntimeException {}
