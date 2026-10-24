<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiHealthProbe.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use APIToolkit\Exceptions\UnauthorizedException;
use App\Plugins\PluginHealth;
use Throwable;

/**
 * Leseprobe gegen eine Fremd-API (Aufgabenimporte, Shops, Belegquellen): Erfolg `ok`, abgewiesene Anmeldung
 * `failing/auth`, Drosselung und Fehlerstatus als begrenzte Störung — ohne
 * Tokens oder Antwortinhalte in der Meldung.
 */
final class ApiHealthProbe {
    public static function probe(string $provider, callable $probe): PluginHealth {
        $started = microtime(true);
        try {
            $probe();

            return PluginHealth::ok((string) __('integration.api_health.ok', ['provider' => $provider]))
                ->withLatency((int) ((microtime(true) - $started) * 1000));
        } catch (UnauthorizedException) {
            return PluginHealth::failing((string) __('integration.api_health.auth', ['provider' => $provider]), 'auth');
        } catch (PluginApiException $e) {
            return match (true) {
                $e->isAuthError() => PluginHealth::failing((string) __('integration.api_health.auth', ['provider' => $provider]), 'auth'),
                $e->isRateLimited() => PluginHealth::degraded((string) __('integration.api_health.rate_limited', ['provider' => $provider]), 'rate_limited'),
                default => PluginHealth::degraded((string) __('integration.api_health.api_error', ['provider' => $provider, 'status' => $e->status]), 'api_error'),
            };
        } catch (Throwable) {
            return PluginHealth::failing((string) __('integration.api_health.unreachable', ['provider' => $provider]), 'unreachable');
        }
    }
}
