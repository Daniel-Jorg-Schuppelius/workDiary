<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenAiProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Dto\AiUsage;

/**
 * OpenAI (Feature 025, MVP-408): Responses API (`POST /v1/responses`) —
 * der von OpenAI empfohlene Endpunkt für Neuintegrationen (Recherche
 * 2026-07). Modell ist Pflicht an der Verbindung (kein Default —
 * OpenAI-Modellnamen altern schnell).
 */
class OpenAiProvider extends AbstractLlmProvider {
    protected function baseUrl(): string {
        return rtrim($this->connection->base_url ?: 'https://api.openai.com', '/');
    }

    /** @return array<string, string> */
    protected function headers(): array {
        return ['Authorization' => 'Bearer ' . $this->requireApiKey()];
    }

    public function preflight(): void {
        $this->getJson('/v1/models', ['limit' => 1]);
    }

    protected function complete(string $system, string $user, bool $expectJson = false): Completion {
        $response = $this->postJson('/v1/responses', [
            'model' => $this->requireModel(),
            'max_output_tokens' => self::MAX_OUTPUT_TOKENS,
            'instructions' => $system,
            'input' => $user,
        ]);

        // Responses API: output[] → message-Items → content[] → output_text.
        $text = '';
        foreach ((array) $response->json('output', []) as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text') {
                    $text .= (string) ($content['text'] ?? '');
                }
            }
        }

        return new Completion($text, new AiUsage(
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
        ));
    }
}
