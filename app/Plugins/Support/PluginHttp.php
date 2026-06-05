<?php
/*
 * Created on   : Fri Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginHttp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support;

use Illuminate\Http\Client\{ConnectionException, PendingRequest, RequestException};
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gemeinsame, gehärtete HTTP-Basis für Plugins: einheitlicher User-Agent,
 * sinnvoller Default-Timeout und eine konservative Retry-Policy (nur bei
 * Rate-Limit 429 und transienten Verbindungsfehlern, mit Backoff inkl.
 * `Retry-After`). `throw: false` → nach Ausschöpfen der Versuche kommt die
 * (Fehler-)Antwort regulär zurück und wird vom Aufrufer behandelt.
 *
 * Plugins setzen Auth/Header/Body weiterhin selbst auf dem zurückgegebenen
 * {@see PendingRequest} (z. B. ->withToken(), ->withHeaders(), ->timeout()).
 */
class PluginHttp {
    public static function for(string $pluginId, int $timeout = 10): PendingRequest {
        return Http::withUserAgent('workDiary-plugin/' . $pluginId)
            ->timeout($timeout)
            ->retry(3, self::backoffMs(...), self::shouldRetry(...), throw: false);
    }

    /** Nur bei Rate-Limit (429) und Verbindungsfehlern erneut versuchen. */
    public static function shouldRetry(Throwable $e): bool {
        if ($e instanceof ConnectionException) {
            return true;
        }

        return $e instanceof RequestException && $e->response->status() === 429;
    }

    /** Backoff in Millisekunden; respektiert den `Retry-After`-Header. */
    public static function backoffMs(int $attempt, Throwable $e): int {
        if ($e instanceof RequestException) {
            $retryAfter = (int) $e->response->header('Retry-After');
            if ($retryAfter > 0) {
                return $retryAfter * 1000;
            }
        }

        return $attempt * 500;
    }
}
