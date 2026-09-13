<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherFileMissingException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice;

use RuntimeException;

/**
 * Zum Beleg gibt es bei Lexoffice kein Belegbild: kein files-Eintrag am Beleg,
 * kein gerendertes Dokument, oder die Datei-Referenz läuft ins Leere (404).
 * Kein Fehler des Plugins — die Materialisierung markiert den Beleg als
 * geprüft. Auth-, Netz- und Serverfehler bleiben eine RuntimeException und
 * dürfen NIE als „kein Belegbild" verbucht werden.
 */
class LexofficeVoucherFileMissingException extends RuntimeException {}
