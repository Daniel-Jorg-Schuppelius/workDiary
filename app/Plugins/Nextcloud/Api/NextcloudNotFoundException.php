<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudNotFoundException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Nextcloud\Api;

use RuntimeException;

/**
 * Ein WebDAV-Ziel (Ordner/Datei) existiert nicht (HTTP 404). Der Intake-Scan
 * behandelt einen verschwundenen UNTERordner tolerant (überspringen), einen
 * fehlenden STAMMordner dagegen als Verbindungsfehler — nie als „alles gelöscht".
 */
class NextcloudNotFoundException extends RuntimeException {}
