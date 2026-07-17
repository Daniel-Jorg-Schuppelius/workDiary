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
     * POST mit JSON-Body; wirft AiProviderCallException bei Transport-
     * oder HTTP-Fehlern (redigiert). Pfade werden — wie im Haus üblich
     * (vgl. TodoistApiClient) — als volle URL an den Toolkit-Client
     * gegeben, damit Basis-URLs mit Pfadanteil (z. B. `…/v1`) erhalten
     * bleiben.
     *
     * @param array<string, mixed> $payload
     */
    protected function postJson(string $path, array $payload): Response {
        try {
            $response = $this->api()->postJson($this->baseUrl() . $path, $payload);
        } catch (AiProviderCallException $e) {
            throw $e; // z. B. Konfigurations-/Tarif-Sperren aus headers()
        } catch (Throwable) {
            throw AiProviderCallException::transport($this->providerName(), 'Transportfehler bei ' . $path);
        }

        return $this->assertOk($response, $path);
    }

    /** @param array<string, mixed> $query */
    protected function getJson(string $path, array $query = []): Response {
        try {
            $response = $this->api()->getResponse($this->baseUrl() . $path, $query);
        } catch (AiProviderCallException $e) {
            throw $e;
        } catch (Throwable) {
            throw AiProviderCallException::transport($this->providerName(), 'Transportfehler bei ' . $path);
        }

        return $this->assertOk($response, $path);
    }

    private function assertOk(Response $response, string $path): Response {
        if ($response->status() >= 400) {
            throw AiProviderCallException::transport(
                $this->providerName(),
                sprintf('HTTP %d bei %s', $response->status(), $path)
            );
        }

        return $response;
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
