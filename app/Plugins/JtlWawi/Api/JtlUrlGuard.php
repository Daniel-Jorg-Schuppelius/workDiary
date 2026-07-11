<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlUrlGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Api;

use App\Models\JtlConnection;
use App\Plugins\JtlWawi\JtlWawiPlugin;
use App\Support\UrlSafety;
use RuntimeException;

/**
 * SSRF-Leitplanke der JTL-Verbindung (Feature 078, MVP-317). Eine
 * OnPremise-Wawi steht typischerweise im Kundennetz — deshalb gibt es je
 * Verbindung den auditierten Schalter `allow_private_network`:
 *
 * - AUS (Default): Ziel muss öffentlich routbar sein
 *   ({@see UrlSafety::isPubliclyRoutableHttpUrl}, DNS-Rebinding-sicher).
 * - AN: private/interne Adressen sind bewusst freigegeben; es bleiben
 *   Schema- (http/https) und Host-Grundprüfung.
 *
 * Der Schalter wird nur von Organisationsadmins gesetzt und ist Teil des
 * Audit-Trails der Verbindung.
 */
final class JtlUrlGuard {
    /** Liefert die geprüfte Basis-URL der Verbindung (ohne Slash am Ende). */
    public static function baseUrlFor(JtlConnection $connection): string {
        if (! $connection->isOnPremise()) {
            return rtrim((string) config('plugins.' . JtlWawiPlugin::ID . '.cloud_base_url'), '/');
        }

        $url = trim((string) $connection->base_url);
        if ($url === '') {
            throw new RuntimeException('JTL-Wawi: Für die OnPremise-Betriebsart ist keine Basis-URL hinterlegt.');
        }

        self::assertAcceptable($url, $connection->allow_private_network);

        return rtrim($url, '/');
    }

    /** Konfigurations- und Laufzeitprüfung einer OnPremise-Basis-URL. */
    public static function assertAcceptable(string $url, bool $allowPrivateNetwork): void {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('JTL-Wawi: Die Basis-URL ist keine gültige http(s)-Adresse.');
        }

        if (! $allowPrivateNetwork && ! UrlSafety::isPubliclyRoutableHttpUrl($url)) {
            throw new RuntimeException(
                'JTL-Wawi: Die Basis-URL zeigt auf eine private/interne Adresse. '
                . 'Für eine OnPremise-Wawi im eigenen Netz muss die Freigabe privater Adressen ausdrücklich aktiviert werden.'
            );
        }
    }
}
