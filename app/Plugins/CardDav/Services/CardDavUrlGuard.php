<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavUrlGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Services;

use App\Support\UrlSafety;
use RuntimeException;

/**
 * SSRF-Leitplanke der CardDAV-Anbindung (Bauturbo A9, MVP-329). Ein
 * self-hosted CardDAV-Server (Nextcloud/Radicale/Baïkal) steht häufig im
 * Kundennetz — deshalb gibt es je Verbindung den auditierten Schalter
 * `allow_private_network` (Muster {@see \App\Plugins\JtlWawi\Api\JtlUrlGuard}):
 *
 * - AUS (Default): Ziel muss öffentlich routbar sein
 *   ({@see UrlSafety::isPubliclyRoutableHttpUrl}, DNS-Rebinding-sicher).
 * - AN: private/interne Adressen sind bewusst freigegeben; es bleiben
 *   Schema- (http/https) und Host-Grundprüfung.
 */
final class CardDavUrlGuard {
    /** Konfigurations- und Laufzeitprüfung einer CardDAV-Basis-URL. */
    public static function assertAcceptable(string $url, bool $allowPrivateNetwork): void {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('CardDAV: Die Basis-URL ist keine gültige http(s)-Adresse.');
        }

        if (! $allowPrivateNetwork && ! UrlSafety::isPubliclyRoutableHttpUrl($url)) {
            throw new RuntimeException(
                'CardDAV: Die Basis-URL zeigt auf eine private/interne Adresse. '
                . 'Für einen Server im eigenen Netz muss die Freigabe privater Adressen ausdrücklich aktiviert werden.'
            );
        }
    }
}
