<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsanaApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Asana\Exceptions;

use App\Plugins\Support\PluginApiException;

/** Fehlerantwort der Asana-API; die Meldung trägt nie Tokens. */
class AsanaApiException extends PluginApiException {}
