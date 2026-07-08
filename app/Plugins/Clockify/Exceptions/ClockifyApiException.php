<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Clockify\Exceptions;

use RuntimeException;

/**
 * Fehlgeschlagener Clockify-API-Aufruf (Nicht-2xx nach ausgeschöpften Retries).
 * Trägt den HTTP-Status für die Fehlerausgabe im Admin-UI; 429 auf dem
 * Free-Plan (30 Requests/h) wird mit CSV-Hinweis gemeldet.
 */
class ClockifyApiException extends RuntimeException {
    public function __construct(string $message, public readonly int $status = 0) {
        parent::__construct($message);
    }
}
