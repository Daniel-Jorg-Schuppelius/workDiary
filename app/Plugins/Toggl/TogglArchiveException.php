<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglArchiveException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl;

use RuntimeException;

/**
 * Signalisiert einen fehlgeschlagenen Export-ZIP-Upload (ungültige Datei bzw.
 * nicht entpackbar). Die Nachricht ist bereits übersetzt und wird vom
 * {@see \App\Plugins\Toggl\Http\Controllers\TogglController::uploadExport()}
 * als Formularfehler (`archive`) zurückgegeben.
 */
class TogglArchiveException extends RuntimeException {}
