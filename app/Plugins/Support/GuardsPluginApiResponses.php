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
 *
 * Daneben (W3d) die assertOk()-Variante mit Detail-Auszug
 * (`message` aus dem JSON-Body, sonst Roh-Body) und optionalem
 * {@see apiErrorHint()}-Zusatz — ersetzt die Kopien in Kimai/Clockify.
 */
trait GuardsPluginApiResponses {
    /** @return class-string<PluginApiException> */
    abstract protected function apiExceptionClass(): string;

    /** Anzeigename des Providers in Fehlermeldungen (z. B. "GitHub"). */
    abstract protected function apiLabel(): string;

    /** Optionaler Zusatzhinweis hinter der Fehlermeldung (z. B. Rate-Limit-Tipp); leer = keiner. */
    protected function apiErrorHint(Response $response): string {
        return '';
    }

    /**
     * Wirft bei Fehlerantworten die provider-eigene Exception mit
     * Detail-Auszug: bevorzugt `message` aus dem JSON-Body, sonst der
     * Roh-Body, auf 300 Zeichen gekürzt — nie Secrets.
     */
    protected function assertOk(Response $response, string $context): void {
        if ($response->successful()) {
            return;
        }

        $detail = (string) ($response->json('message') ?? $response->body());
        $message = sprintf('%s %s: HTTP %d — %s', $this->apiLabel(), $context, $response->status(), mb_substr($detail, 0, 300));
        $hint = $this->apiErrorHint($response);
        if ($hint !== '') {
            $message .= ' ' . $hint;
        }

        $class = $this->apiExceptionClass();

        throw new $class($message, $response->status());
    }

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
