<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConnectionFailure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use APIToolkit\Exceptions\{ApiException, UnauthorizedException};
use Throwable;

/**
 * Einordnung eines Verbindungsfehlers (MVP-1620): Eine abgelaufene, widerrufene
 * oder abgewiesene Anmeldung (HTTP 401, nicht erneuerbares Token) heilt kein
 * weiterer Versuch — die Verbindung braucht eine neue Anmeldung. 403 zählt
 * bewusst nicht dazu, weil Anbieter wie Google damit auch Ratenlimits melden.
 */
final class ConnectionFailure {
    public static function requiresReauth(Throwable $error): bool {
        for ($current = $error; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof UnauthorizedException
                || ($current instanceof PluginApiException && $current->status === 401)
                || ($current instanceof ApiException && (int) $current->getCode() === 401)) {
                return true;
            }
        }

        return false;
    }
}
