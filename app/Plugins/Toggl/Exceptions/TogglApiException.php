<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl\Exceptions;

use App\Plugins\Support\PluginApiException;

/**
 * Fehlgeschlagener Toggl-API-Aufruf (Nicht-2xx). Trägt den HTTP-Status für
 * die Fehlerausgabe im Admin-UI und die Rate-Limit-Abbruchbedingung (429).
 */
class TogglApiException extends PluginApiException {
    public function __construct(string $message, int $status = 0) {
        parent::__construct($message, $status);
    }
}
