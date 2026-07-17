<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AnthropicProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Dto\AiUsage;

/**
 * Anthropic Claude (Feature 025, MVP-407): Messages API
 * (`POST /v1/messages`, Header `x-api-key` + `anthropic-version`).
 * Preflight über die kostenlose Models-API. Ohne konfiguriertes Modell
 * wird `claude-opus-4-8` verwendet.
 */
class AnthropicProvider extends AbstractLlmProvider {
    public const DEFAULT_MODEL = 'claude-opus-4-8';

    private const API_VERSION = '2023-06-01';

    protected function baseUrl(): string {
        return rtrim($this->connection->base_url ?: 'https://api.anthropic.com', '/');
    }

    /** @return array<string, string> */
    protected function headers(): array {
        return [
            'x-api-key' => $this->requireApiKey(),
            'anthropic-version' => self::API_VERSION,
        ];
    }

    public function preflight(): void {
        $this->getJson('/v1/models', ['limit' => 1]);
    }

    protected function complete(string $system, string $user, bool $expectJson = false): Completion {
        $response = $this->postJson('/v1/messages', [
            'model' => trim((string) $this->connection->model) !== '' ? $this->connection->model : self::DEFAULT_MODEL,
            'max_tokens' => self::MAX_OUTPUT_TOKENS,
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $user],
            ],
        ]);

        $text = '';
        foreach ((array) $response->json('content', []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return new Completion($text, new AiUsage(
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
        ));
    }
}
