<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeantimeVersionUnsupportedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Leantime\Exceptions;

use RuntimeException;

/** Leantime vor 3.8 verlangt für die Projektliste die Benutzer-ID, die ein API-Schlüssel nicht kennt. */
class LeantimeVersionUnsupportedException extends RuntimeException {}
