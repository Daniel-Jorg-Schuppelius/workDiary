<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenAiCompatibleProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Dto\AiUsage;
use App\Services\Ai\Exceptions\AiProviderCallException;

/**
 * Generischer OpenAI-kompatibler Adapter (Feature 025, MVP-407): deckt
 * vLLM, LM Studio, LiteLLM, Groq, OpenRouter, IONOS AI Model Hub u. a.
 * über Basis-URL + Bearer-Key ab. Die Basis-URL enthält den
 * Versionspräfix (z. B. `https://host/v1`). Structured Output ist an
 * den Rändern nicht einheitlich — Klassifikation läuft deshalb über
 * Prompt + toleranten Parser (Katalog-Garantie greift typseitig).
 */
class OpenAiCompatibleProvider extends AbstractLlmProvider {
    protected function baseUrl(): string {
        $base = rtrim((string) $this->connection->base_url, '/');
        if ($base === '') {
            throw AiProviderCallException::transport($this->providerName(), (string) __('ai.error.base_url_missing'));
        }

        return $base;
    }

    /** @return array<string, string> */
    protected function headers(): array {
        $key = (string) $this->connection->api_key;

        return $key === '' ? [] : ['Authorization' => 'Bearer ' . $key];
    }

    public function preflight(): void {
        $this->getJson('/models');
    }

    protected function complete(string $system, string $user, bool $expectJson = false): Completion {
        $response = $this->postJson('/chat/completions', [
            'model' => $this->requireModel(),
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
