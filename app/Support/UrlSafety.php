<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UrlSafety.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use CommonToolkit\Helper\Data\IPHelper;

/**
 * SSRF-Schutz für ausgehende, von Nutzern konfigurierte Ziel-URLs (z. B.
 * Webhooks). Lässt nur öffentlich routbare http(s)-Ziele zu und blockiert
 * Loopback, private (RFC1918), link-local (inkl. Cloud-Metadata 169.254.169.254)
 * und reservierte Bereiche — sowohl bei der Konfiguration als auch zur Laufzeit
 * (gegen DNS-Rebinding).
 */
final class UrlSafety {
    /**
     * Schnelle Konfigurationszeit-Prüfung OHNE DNS-Auflösung (nicht blockierend):
     * gültiges http(s)-Ziel, dessen Host kein offensichtlich internes Ziel ist
     * (Loopback-Name oder IP-Literal im privaten/reservierten Bereich). Die
     * verbindliche, DNS-Rebinding-sichere SSRF-Prüfung erfolgt zur Laufzeit in
     * {@see isPubliclyRoutableHttpUrl}. Hier bewusst keine DNS-Auflösung, damit
     * das Speichern eines Endpunkts nicht auf langsamen/timenden DNS-Lookups hängt.
     */
    public static function isAcceptableExternalHttpUrl(string $url): bool {
        $parts = parse_url(trim($url));
        if ($parts === false || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', 'localhost.localdomain', 'ip6-localhost'], true)) {
            return false;
        }

        // IP-Literal? Dann muss es bereits hier öffentlich sein (kein DNS nötig).
        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return IPHelper::isPublicIP($literal);
        }

        return true; // Hostname: finale Prüfung (DNS) erfolgt zur Laufzeit.
    }

    /**
     * Verbindliche Laufzeit-Prüfung: gültiges http(s)-Ziel, dessen Host zu
     * ausschließlich öffentlichen IPs auflöst (DNS-Rebinding-sicher).
     */
    public static function isPubliclyRoutableHttpUrl(string $url): bool {
        $parts = parse_url(trim($url));
        if ($parts === false || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }

        $host = (string) ($parts['host'] ?? '');
        if ($host === '') {
            return false;
        }

        // Blockiert wird ausschließlich, was zu einem internen/privaten Ziel
        // auflöst. Ein nicht auflösbarer Host ist KEIN SSRF-Ziel (es gibt nichts
        // Internes zu erreichen) – die Verbindung scheitert dann ohnehin harmlos.
        foreach (self::resolveHost($host) as $ip) {
            if (! IPHelper::isPublicIP($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Schnelle Konfigurationszeit-Prüfung eines bloßen Hostnamens (FTP/SFTP)
     * OHNE DNS-Auflösung: kein Loopback-Name und kein IP-Literal im privaten/
     * reservierten Bereich. Die verbindliche Prüfung erfolgt zur Laufzeit in
     * {@see isPubliclyRoutableHost}.
     */
    public static function isAcceptableExternalHost(string $host): bool {
        $host = strtolower(trim($host));
        if ($host === '' || in_array($host, ['localhost', 'localhost.localdomain', 'ip6-localhost'], true)) {
            return false;
        }

        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return IPHelper::isPublicIP($literal);
        }

        return true;
    }

    /**
     * Verbindliche Laufzeit-Prüfung eines bloßen Hostnamens (FTP/SFTP): der
     * Host muss zu ausschließlich öffentlichen IPs auflösen (DNS-Rebinding-
     * sicher). Ein nicht auflösbarer Host ist kein SSRF-Ziel und bleibt zulässig.
     */
    public static function isPubliclyRoutableHost(string $host): bool {
        $host = trim($host);
        if (! self::isAcceptableExternalHost($host)) {
            return false;
        }

        foreach (self::resolveHost($host) as $ip) {
            if (! IPHelper::isPublicIP($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sicheres Redirect-Ziel? Nur absolute Pfade auf demselben Host bzw.
     * relative Pfade (beginnend mit „/", aber kein protokoll-relatives „//host").
     * Verhindert Open-Redirects auf fremde Domains.
     */
    public static function isSameOriginOrRelative(string $url, string $appHost): bool {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        $host = $parts['host'] ?? null;
        if ($host === null) {
            return str_starts_with($url, '/'); // relativer Pfad
        }

        return strcasecmp($host, $appHost) === 0;
    }

    /**
     * Auflösung des Hosts in IP-Adressen (v4 + v6). Ist der Host bereits eine
     * IP-Literal-Angabe, wird diese direkt geprüft.
     *
     * @return list<string>
     */
    private static function resolveHost(string $host): array {
        $host = trim($host, '[]'); // IPv6-Literale wie [::1]
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];
        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }
        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $record) {
                if (! empty($record['ipv6'])) {
                    $ips[] = (string) $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
