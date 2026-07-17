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

use App\Services\Ai\Dto\AiUsage;
use App\Services\Ai\Exceptions\AiProviderCallException;

/**
 * Azure OpenAI (Feature 025, MVP-408): v1-Endpunkt der Ressource
 * (`https://<resource>.openai.azure.com`), Auth per `api-key`-Header,
 * `model` = Deployment-Name. EU-Data-Zone ist eine Deployment-
 * Eigenschaft in Azure — Hinweis dazu in der Verbindungs-Hilfe.
 */
class AzureOpenAiProvider extends AbstractLlmProvider {
    protected function baseUrl(): string {
        $base = rtrim((string) $this->connection->base_url, '/');
        if ($base === '') {
            throw AiProviderCallException::transport($this->providerName(), 'Keine Ressourcen-URL an der Verbindung hinterlegt.');
        }

        return $base;
    }

    /** @return array<string, string> */
    protected function headers(): array {
        return ['api-key' => $this->requireApiKey()];
    }

    public function preflight(): void {
        $this->getJson('/openai/v1/models');
    }

    protected function complete(string $system, string $user, bool $expectJson = false): Completion {
        $response = $this->postJson('/openai/v1/chat/completions', [
            'model' => $this->requireModel(), // Deployment-Name
            'max_tokens' => self::MAX_OUTPUT_TOKENS,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ]);

        return new Completion((string) $response->json('choices.0.message.content', ''), new AiUsage(
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
        ));
    }
}
