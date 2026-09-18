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
use CommonToolkit\Helper\Data\WebLinkHelper;

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
        // Gemeinsamer Guard (Vollaudit 2026-07, M48) — Meldungstexte unverändert.
        UrlSafety::assertAcceptableExternalBaseUrl(
            $url,
            $allowPrivateNetwork,
            'CardDAV',
            privateHint: 'Für einen Server im eigenen Netz muss die Freigabe privater Adressen ausdrücklich aktiviert werden.',
        );
    }

    /**
     * Adressbuch- und Discovery-Adressen kommen aus der Antwort des Servers
     * (hrefs, SRV-Einträge). Sie gelten nur, wenn sie zur konfigurierten
     * Verbindung gehören — sonst führte ein Server den Abruf an der geprüften
     * Basis-URL vorbei (Sicherheitsaudit 2026-09-17, ssrf-6).
     */
    public static function assertSameOriginAsBase(string $url, string $baseUrl, bool $allowPrivateNetwork): void {
        self::assertAcceptable($url, $allowPrivateNetwork);

        // Fail-closed: ohne bestimmbaren Ursprung gilt die Adresse als fremd.
        $origin = WebLinkHelper::origin(trim($url));
        if ($origin === null || $origin !== WebLinkHelper::origin(trim($baseUrl))) {
            throw new \RuntimeException((string) __('carddav.flash.foreign_origin'));
        }
    }
}
