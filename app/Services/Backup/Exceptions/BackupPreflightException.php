<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupPreflightException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup\Exceptions;

use RuntimeException;

/**
 * On-Premise-Voraussetzung fehlt (tar/mysqldump/pg_dump nicht auffindbar,
 * Arbeitsverzeichnis nicht beschreibbar, Quota zu knapp) — klarer Abbruch
 * VOR dem Lauf statt stillem Fail.
 */
class BackupPreflightException extends RuntimeException {}
