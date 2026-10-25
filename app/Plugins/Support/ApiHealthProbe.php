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

use APIToolkit\Exceptions\ApiException;
use App\Plugins\PluginHealth;
use Illuminate\Http\Client\RequestException;
use Psr\Http\Client\NetworkExceptionInterface;
use Throwable;

/**
 * Die eine Leseprobe aller Plugin-Healthchecks gegen eine Fremd-API: Vorübergehendes (Netz, Zeitüberschreitung,
 * 5xx, 429) ist `degraded` und zählt nicht zur Abschaltung, Dauerhaftes (abgewiesene Anmeldung, übrige 4xx,
 * blockiertes Ziel, unerwartete Ausnahme) ist `failing`. Meldungen tragen Status oder Ausnahmeklasse, nie
 * Tokens oder Antwortinhalte.
 */
final class ApiHealthProbe {
    /**
     * @param  callable(): mixed  $probe  wirft bei Fehlern; eine zurückgegebene Zeichenkette ist die Erfolgsmeldung,
     *                                    eine PluginHealth ein fachlicher Befund der Probe
     * @param  array{auth?: string, rate_limited?: string}  $hints  Anbietertext statt des allgemeinen; `:status` wird ersetzt
     */
    public static function probe(string $provider, callable $probe, array $hints = []): PluginHealth {
        try {
            $result = $probe();
        } catch (Throwable $e) {
            return self::classify($provider, $e, $hints);
        }

        if ($result instanceof PluginHealth) {
            return $result;
        }

        return PluginHealth::ok(is_string($result) && $result !== ''
            ? $result
            : (string) __('integration.api_health.ok', ['provider' => $provider]));
    }

    /** @param  array{auth?: string, rate_limited?: string}  $hints */
    private static function classify(string $provider, Throwable $e, array $hints): PluginHealth {
        // Auch eingepackt (SDK-Fehler mit Guzzle-Ursache) bleibt ein Netzfehler vorübergehend.
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof NetworkExceptionInterface) {
                return PluginHealth::degraded((string) __('integration.api_health.network', ['provider' => $provider]), 'network');
            }
        }

        return match (true) {
            // Ohne HTTP-Status hat der Client selbst abgebrochen (internes Ziel, fremder Host, ungültige Auswahl).
            $e instanceof PluginApiException && $e->status === 0 => PluginHealth::failing($e->getMessage(), 'aborted'),
            $e instanceof PluginApiException => self::rejected($provider, $e->status, $hints),
            $e instanceof ApiException => self::rejected($provider, (int) $e->getCode(), $hints),
            $e instanceof RequestException => self::rejected($provider, $e->response->status(), $hints),
            default => PluginHealth::failing((string) __('integration.api_health.error', ['provider' => $provider, 'class' => class_basename($e)]), 'error'),
        };
    }

    /** @param  array{auth?: string, rate_limited?: string}  $hints */
    private static function rejected(string $provider, int $status, array $hints): PluginHealth {
        $context = ['provider' => $provider, 'status' => $status];
        $hint = static fn (string $key): ?string => isset($hints[$key]) ? strtr($hints[$key], [':status' => (string) $status]) : null;

        return match (true) {
            $status === 401, $status === 403 => PluginHealth::failing($hint('auth') ?? (string) __('integration.api_health.auth', $context), 'auth'),
            $status === 429 => PluginHealth::degraded($hint('rate_limited') ?? (string) __('integration.api_health.rate_limited', $context), 'rate_limited'),
            $status === 408, $status >= 500 => PluginHealth::degraded((string) __('integration.api_health.server_error', $context), 'server_error'),
            default => PluginHealth::failing((string) __('integration.api_health.api_error', $context), 'api_error'),
        };
    }
}
