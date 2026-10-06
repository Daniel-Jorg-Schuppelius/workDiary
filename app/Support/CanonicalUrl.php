<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CanonicalUrl.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Absolute Adressen aus der **konfigurierten** Adresse der Installation.
 *
 * `route()` bildet die Wurzel aus dem Host-Header (bei `TRUSTED_PROXIES=*`
 * aus `X-Forwarded-Host`). Wo ein anonymer Aufruf eine Adresse erzeugt, die
 * das Haus verlässt — Reset-Mail (S-11), Rücksprung und Webhook einer
 * Bezahlseite (Sicherheitsaudit 2026-10-04, pub-1) —, darf sie nicht vom
 * Aufrufer stammen. `app.url` gewinnt, solange sie gesetzt und nicht die
 * lokale Entwicklungsadresse ist.
 */
final class CanonicalUrl {
    public static function base(): string {
        $configured = rtrim((string) config('app.url', ''), '/');
        $host = parse_url($configured, PHP_URL_HOST);

        return is_string($host) && $host !== '' && ! in_array($host, ['localhost', '127.0.0.1'], true)
            ? $configured
            : rtrim(url('/'), '/');
    }

    /** @param array<string, mixed>|string|int|\Illuminate\Database\Eloquent\Model $parameters */
    public static function route(string $name, mixed $parameters = []): string {
        return self::base() . route($name, $parameters, absolute: false);
    }
}
