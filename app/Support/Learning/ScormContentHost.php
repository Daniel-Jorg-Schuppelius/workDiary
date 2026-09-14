<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormContentHost.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Learning;

use CommonToolkit\Helper\Data\WebLinkHelper;

/**
 * Der eigene Host für SCORM-Kursinhalte ({@see config('learning.scorm.content_url')}).
 *
 * Funktioniert als Subdomain und als eigene Domain. Ist der Inhalts-Ursprung
 * gleich dem der Anwendung, gilt er als nicht konfiguriert — sonst würde die
 * Sperre für App-Routen auf dem Inhalts-Host die ganze Anwendung abschalten.
 */
final class ScormContentHost {
    public static function origin(): ?string {
        $origin = WebLinkHelper::origin((string) config('learning.scorm.content_url', ''));

        if ($origin === null || $origin === self::appOrigin()) {
            return null;
        }

        return $origin;
    }

    public static function host(): ?string {
        $origin = self::origin();

        return $origin === null ? null : WebLinkHelper::getHost($origin);
    }

    public static function isConfigured(): bool {
        return self::origin() !== null;
    }

    public static function appOrigin(): ?string {
        return WebLinkHelper::origin((string) config('app.url', ''));
    }

    /**
     * Name des Sitzungscookies.
     *
     * Eine Subdomain gilt als dieselbe Site: Code vom Inhalts-Host könnte ein Cookie
     * für die Hauptdomain setzen und damit eine Sitzung unterschieben. Ein Cookie mit
     * dem Präfix `__Host-` kann nur der eigene Host setzen. Der Browser verlangt dafür
     * `Secure`, Pfad `/` und keine Domain — fehlt eins davon, bleibt der Name, wie er ist.
     */
    public static function sessionCookieName(string $name, bool $secure, ?string $domain, ?string $contentUrl): string {
        if ($contentUrl === null || $contentUrl === '' || ! $secure || ($domain !== null && $domain !== '') || str_starts_with($name, '__Host-')) {
            return $name;
        }

        return '__Host-' . $name;
    }
}
