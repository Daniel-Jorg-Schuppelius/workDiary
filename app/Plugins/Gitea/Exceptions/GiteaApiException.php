<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GiteaApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitea\Exceptions;

use App\Plugins\Support\PluginApiException;

/** Fehlerantwort einer Gitea-/Forgejo-Instanz; die Meldung trägt nie den Token. */
class GiteaApiException extends PluginApiException {}
