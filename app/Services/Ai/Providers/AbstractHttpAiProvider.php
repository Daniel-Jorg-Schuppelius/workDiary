<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractHttpAiProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use APIToolkit\Exceptions\{ApiException, TooManyRequestsException};
use App\Models\Ai\AiProviderConnection;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Services\Ai\Exceptions\AiProviderCallException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * HTTP-Fundament aller KI-Adapter (Feature 025, MVP-407): Client über die
 * PluginHttpFactory (Toolkit-Transport mit Retry/Backoff/Retry-After;
 * Tests ersetzen die Factory durch {@see \Tests\Support\FakePluginHttp}).
 * Fehlerdisziplin: nach außen ausschließlich
 * {@see AiProviderCallException} mit redigierter Meldung — nie
 * Prompt-Inhalte, nie Schlüssel, nur Status + Pfadkontext.
 */
abstract class AbstractHttpAiProvider {
    private ?PluginApiClient $api = null;

    public function __construct(protected readonly AiProviderConnection $connection) {}

    /** Basis-URL des Providers (Verbindungs-Override vor Default). */
    abstract protected function baseUrl(): string;

    /** @return array<string, string> Auth-/Standard-Header */
    abstract protected function headers(): array;

    protected function providerName(): string {
        return $this->connection->provider->value;
    }

    protected function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('ai-' . $this->providerName(), $this->baseUrl());
            $this->api->setDefaultHeaders($this->headers());
        }

        return $this->api;
    }

    /**
     * POST mit JSON-Body; wirft AiProviderCallException bei Transport-
     * oder HTTP-Fehlern (redigiert). Konfigurations-/Tarif-Sperren aus
     * baseUrl()/headers() werfen bereits beim Clientbau in buildUrl().
     *
     * @param array<string, mixed> $payload
     */
    protected function postJson(string $path, array $payload): Response {
        $url = $this->api()->buildUrl($path);

        try {
            // LLM-Generate-Calls sind POSTs ohne serverseitigen Zustand — der
            // 429/5xx-Retry des Toolkits ist hier gewollt (Provider-Rate-
            // Limits) und seit api-toolkit v2.9.2 für POST Opt-in.
            $response = $this->api()->postJson($url, $payload, ['retry_non_idempotent' => true]);
        } catch (Throwable) {
            throw AiProviderCallException::transport(
                $this->providerName(),
                (string) __('ai.error.transport', ['url' => self::redactUrl($url)])
            );
        }

        return $this->assertOk($response, $url);
    }

    /** @param array<string, mixed> $query */
    protected function getJson(string $path, array $query = []): Response {
        $url = $this->api()->buildUrl($path);

        try {
            $response = $this->api()->getResponse($url, $query);
        } catch (Throwable) {
            throw AiProviderCallException::transport(
                $this->providerName(),
                (string) __('ai.error.transport', ['url' => self::redactUrl($url)])
            );
        }

        return $this->assertOk($response, $url);
    }

    protected function assertOk(Response $response, string $url): Response {
        if ($response->status() >= 400) {
            throw AiProviderCallException::transport(
                $this->providerName(),
                (string) __('ai.error.http_status', ['status' => $response->status(), 'url' => self::redactUrl($url)])
                    . self::providerDetail($response)
            );
        }

        return $response;
    }

    /**
     * Klartext des Anbieters zum Fehler — „HTTP 429" allein schickt einen auf
     * die falsche Fährte (Warten hilft nicht, wenn das Kontingent leer ist).
     * Kontingent-Erkennung + Code-Extraktion kommen aus dem api-toolkit ≥ 2.9.
     *
     * Bewusst NICHT bei 400/422: dort echoen Anbieter gern das fehlerhafte
     * Feld samt Inhalt zurück, und Prompt-Inhalte gehören nicht ins
     * Health-Tracking. Der Fehlercode ist maschinell und immer unbedenklich.
     */
    protected static function providerDetail(Response $response): string {
        $status = $response->status();
        $psr = $response->toPsrResponse();

        if (TooManyRequestsException::isQuotaResponse($psr)) {
            return ' — ' . (string) __('ai.error.provider_quota');
        }

        // Vor json() lesen: contentOf() im Toolkit startet an der aktuellen
        // Stream-Position und sähe nach Illuminate-Reads nur noch Leerstring.
        $code = ApiException::errorCodesOf($psr)[0] ?? '';

        /** @var array<string, mixed> $body */
        $body = (array) $response->json();
        /** @var array<string, mixed> $error */
        $error = (array) ($body['error'] ?? $body);

        $parts = array_filter([
            $code,
            $status === 400 || $status === 422
                ? ''
                : mb_substr(is_scalar($error['message'] ?? null) ? trim((string) $error['message']) : '', 0, 160),
        ], static fn(string $part): bool => $part !== '');

        return $parts === [] ? '' : ' — ' . implode(': ', $parts);
    }

    /**
     * Meldungstauglicher Endpunkt: volle URL ohne Query — der Host gehört
     * zur Diagnose (falsche Basis-URL), Query-Parameter nicht.
     */
    protected static function redactUrl(string $url): string {
        return explode('?', $url, 2)[0];
    }

    /** Pflichtwert aus der Verbindung (Modell/Deployment/Basis-URL). */
    protected function requireModel(): string {
        $model = trim((string) $this->connection->model);
        if ($model === '') {
            throw AiProviderCallException::transport($this->providerName(), (string) __('ai.error.model_missing'));
        }

        return $model;
    }

    protected function requireApiKey(): string {
        $key = (string) $this->connection->api_key;
        if ($key === '') {
            throw AiProviderCallException::transport($this->providerName(), (string) __('ai.error.api_key_missing'));
        }

        return $key;
    }
}
