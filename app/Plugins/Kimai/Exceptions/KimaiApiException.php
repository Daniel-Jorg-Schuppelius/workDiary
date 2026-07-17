<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kimai\Exceptions;

use App\Plugins\Support\PluginApiException;

/**
 * Fehlgeschlagener Kimai-API-Aufruf (Nicht-2xx nach ausgeschöpften Retries).
 * Trägt den HTTP-Status für die Fehlerausgabe im Admin-UI.
 */
class KimaiApiException extends PluginApiException {
    public function __construct(string $message, int $status = 0) {
        parent::__construct($message, $status);
    }
}
