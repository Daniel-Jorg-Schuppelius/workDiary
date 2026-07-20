<?php
/*
 * Created on   : Sat Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuardsPluginApiResponses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use Illuminate\Http\Client\Response;

/**
 * Gemeinsame guard()-Fehlerbehandlung der Plugin-API-Clients
 * (Vollaudit 2026-07, N33) — ersetzt sieben wortgleiche Kopien. Jeder Client
 * behält seine Exception-Subklasse (catch-Selektivität je Provider, B4-Linie)
 * und liefert sie über apiExceptionClass()/apiLabel(). Die Message trägt nur
 * Status und gekürzten Body-Auszug — nie Secrets.
 */
trait GuardsPluginApiResponses {
    /** @return class-string<PluginApiException> */
    abstract protected function apiExceptionClass(): string;

    /** Anzeigename des Providers in Fehlermeldungen (z. B. "GitHub"). */
    abstract protected function apiLabel(): string;

    /** @return array<mixed> */
    protected function guard(Response $response, string $endpoint): array {
        if (! $response->successful()) {
            throw $this->apiError($response, $endpoint);
        }

        return (array) ($response->json() ?? []);
    }

    /** Baut die provider-eigene Exception zur fehlgeschlagenen Antwort. */
    protected function apiError(Response $response, string $endpoint): PluginApiException {
        $class = $this->apiExceptionClass();

        return new $class(
            sprintf('%s %s: HTTP %d %s', $this->apiLabel(), $endpoint, $response->status(), mb_substr((string) $response->body(), 0, 300)),
            $response->status(),
            $endpoint,
        );
    }
}
