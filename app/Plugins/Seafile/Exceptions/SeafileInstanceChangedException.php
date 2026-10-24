<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafileInstanceChangedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Seafile\Exceptions;

use RuntimeException;

/** Die Plugin-Einstellungen zeigen auf eine andere Instanz als die bestehende Verbindung. */
class SeafileInstanceChangedException extends RuntimeException {}
