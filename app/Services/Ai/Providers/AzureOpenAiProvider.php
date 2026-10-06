<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AzureOpenAiProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Exceptions\AiProviderCallException;

/**
 * Azure OpenAI (Feature 025, MVP-408): v1-Endpunkt der Ressource
 * (`https://<resource>.openai.azure.com`), Auth per `api-key`-Header,
 * `model` = Deployment-Name. EU-Data-Zone ist eine Deployment-
 * Eigenschaft in Azure — Hinweis dazu in der Verbindungs-Hilfe.
 */
class AzureOpenAiProvider extends OpenAiCompatibleProvider {
    protected function baseUrl(): string {
        $base = rtrim((string) $this->connection->base_url, '/');
        if ($base === '') {
            throw AiProviderCallException::transport($this->providerName(), (string) __('ai.error.resource_url_missing'));
        }

        return $base;
    }

    /** @return array<string, string> */
    protected function headers(): array {
        return ['api-key' => $this->requireApiKey()];
    }

    protected function apiPrefix(): string {
        return '/openai/v1';
    }
}
