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
     * Volle Ziel-URL aus Basis + Endpunktpfad. Pfade gehen — wie im Haus
     * üblich (vgl. TodoistApiClient) — als volle URL an den Toolkit-Client,
     * damit Basis-URLs mit Pfadanteil (z. B. `…/v1`) erhalten bleiben.
     *
     * Basis-URLs werden in der Praxis aber auch als *Endpunkt*-URL
     * eingetragen (`https://api.openai.com/v1/responses`); stures Anhängen
     * ergäbe dann `/v1/responses/v1/models` → 404. Taucht das erste
     * Pfadsegment schon im Basis-Pfad auf, wird die Basis ab dessen
     * letztem Vorkommen gekappt (letztes = so wenig wie möglich kappen,
     * damit Gateway-Präfixe wie `…/proxy/v1` heil bleiben).
     */
    protected function url(string $path): string {
        $base = rtrim($this->baseUrl(), '/');
        $suffix = ltrim($path, '/');
        if ($suffix === '') {
            return $base;
        }

        $basePath = trim((string) parse_url($base, PHP_URL_PATH), '/');
        if ($basePath !== '') {
            $baseSegments = explode('/', $basePath);
            $hits = array_keys($baseSegments, explode('/', explode('?', $suffix, 2)[0])[0], true);

            if ($hits !== []) {
                $drop = '/' . implode('/', array_slice($baseSegments, (int) end($hits)));
                if (str_ends_with($base, $drop)) {
                    $base = substr($base, 0, -strlen($drop));
                }
            }
        }

        return $base . '/' . $suffix;
    }

    /**
     * POST mit JSON-Body; wirft AiProviderCallException bei Transport-
     * oder HTTP-Fehlern (redigiert).
     *
     * @param array<string, mixed> $payload
     */
    protected function postJson(string $path, array $payload): Response {
        $url = $this->url($path);

        try {
            $response = $this->api()->postJson($url, $payload);
        } catch (AiProviderCallException $e) {
            throw $e; // z. B. Konfigurations-/Tarif-Sperren aus headers()
        } catch (Throwable) {
            throw AiProviderCallException::transport($this->providerName(), 'Transportfehler bei ' . self::redactUrl($url));
        }

        return $this->assertOk($response, $url);
    }

    /** @param array<string, mixed> $query */
    protected function getJson(string $path, array $query = []): Response {
        $url = $this->url($path);

        try {
            $response = $this->api()->getResponse($url, $query);
        } catch (AiProviderCallException $e) {
            throw $e;
        } catch (Throwable) {
            throw AiProviderCallException::transport($this->providerName(), 'Transportfehler bei ' . self::redactUrl($url));
        }

        return $this->assertOk($response, $url);
    }

    protected function assertOk(Response $response, string $url): Response {
        if ($response->status() >= 400) {
            throw AiProviderCallException::transport(
                $this->providerName(),
                sprintf('HTTP %d bei %s', $response->status(), self::redactUrl($url))
            );
        }

        return $response;
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
            throw AiProviderCallException::transport(
                $this->providerName(),
                'Kein Modell/Deployment an der Verbindung hinterlegt.'
            );
        }

        return $model;
    }

    protected function requireApiKey(): string {
        $key = (string) $this->connection->api_key;
        if ($key === '') {
            throw AiProviderCallException::transport($this->providerName(), 'Kein API-Schlüssel hinterlegt.');
        }

        return $key;
    }
}
