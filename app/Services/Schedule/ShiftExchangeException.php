<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangeException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule;

use RuntimeException;

/**
 * Fachlicher Fehler im Schichttausch-Workflow (ungültiger Statusübergang,
 * Compliance-Blockade bei der Freigabe etc.). Feature 007.
 */
class ShiftExchangeException extends RuntimeException {}
