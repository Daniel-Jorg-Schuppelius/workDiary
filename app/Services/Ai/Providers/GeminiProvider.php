<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeminiProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Dto\AiUsage;

/**
 * Google Gemini (Feature 025, MVP-408): `generateContent` mit
 * `x-goog-api-key`. WICHTIG (Recherche 2026-07): Nur der Paid Tier ist
 * zulässig — der Free Tier nutzt Eingaben zum Training. Das ist per API
 * nicht erkennbar; der Datenschutzhinweis an der Verbindung und die
 * AVV-Doku (MVP-411) tragen die Pflicht, technisch wird sie in der
 * Verbindungs-Hilfe ausgewiesen.
 */
class GeminiProvider extends AbstractLlmProvider {
    protected function baseUrl(): string {
        return rtrim($this->connection->base_url ?: 'https://generativelanguage.googleapis.com', '/');
    }

    /** @return array<string, string> */
    protected function headers(): array {
        return ['x-goog-api-key' => $this->requireApiKey()];
    }

    public function preflight(): void {
        $this->getJson('/v1beta/models', ['pageSize' => 1]);
    }

    protected function complete(string $system, string $user, bool $expectJson = false): Completion {
        $generationConfig = ['maxOutputTokens' => self::MAX_OUTPUT_TOKENS];
        if ($expectJson) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        $response = $this->postJson('/v1beta/models/' . $this->requireModel() . ':generateContent', [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $user]]],
            ],
            'generationConfig' => $generationConfig,
        ]);

        $text = '';
        foreach ((array) $response->json('candidates.0.content.parts', []) as $part) {
            $text .= (string) ($part['text'] ?? '');
        }

        return new Completion($text, new AiUsage(
            inputTokens: (int) $response->json('usageMetadata.promptTokenCount', 0),
            outputTokens: (int) $response->json('usageMetadata.candidatesTokenCount', 0),
        ));
    }
}
