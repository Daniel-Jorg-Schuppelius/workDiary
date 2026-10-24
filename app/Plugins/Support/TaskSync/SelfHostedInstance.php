<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfHostedInstance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;

/**
 * Identität und Links einer selbst gehosteten Instanz (Gitea/Forgejo,
 * Redmine): die Instanz ist Host, Port und Pfad der Basis-URL — Schema,
 * Standardport und Schrägstrich am Ende zählen nicht, damit ein Wechsel auf
 * https die Fremd-IDs nicht neu deutet.
 */
final class SelfHostedInstance {
    /** Kurzer, fester Schlüssel als Präfix der Fremd-IDs. */
    public static function key(string $baseUrl): string {
        return substr((string) CryptoHelper::hash(self::name($baseUrl), HashAlgorithm::SHA256), 0, 32);
    }

    /** Lesbare Form: Host[:Port][/Pfad]. */
    public static function name(string $baseUrl): string {
        $parts = parse_url(trim($baseUrl));
        if (! is_array($parts) || ! isset($parts['host'])) {
            return '';
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $port = isset($parts['port']) && ! in_array([$scheme, (int) $parts['port']], [['https', 443], ['http', 80]], true) ? ':' . $parts['port'] : '';

        return strtolower($parts['host']) . $port . rtrim((string) ($parts['path'] ?? ''), '/');
    }

    /** Zeigt `$url` per http(s) auf diese Instanz? Nur dann wird er verlinkt. */
    public static function owns(string $baseUrl, string $url): bool {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $base = self::name($baseUrl);

        return in_array($scheme, ['http', 'https'], true)
            && $base !== ''
            && str_starts_with(self::name($url) . '/', $base . '/')
            && parse_url($url, PHP_URL_USER) === null;
    }
}
