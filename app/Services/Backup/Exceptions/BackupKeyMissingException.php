<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupKeyMissingException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup\Exceptions;

use RuntimeException;

/** BACKUP_MASTER_KEY fehlt oder ist kein gültiger 32-Byte-base64-Wert. */
class BackupKeyMissingException extends RuntimeException {}
