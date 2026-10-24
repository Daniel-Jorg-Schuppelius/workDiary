<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Jira\Exceptions;

use App\Plugins\Support\PluginApiException;

/** Fehlerantwort der Jira-Cloud-API; die Meldung trägt nie Tokens. */
class JiraApiException extends PluginApiException {}
