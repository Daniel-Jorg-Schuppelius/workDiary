<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebParseException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use RuntimeException;

/** Die GAEB-Datei ist syntaktisch unlesbar (kein gültiges XML / kein GAEB-Wurzelelement). */
class GaebParseException extends RuntimeException {}
