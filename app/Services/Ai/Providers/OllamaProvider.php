<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OllamaProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Dto\AiUsage;

/**
 * Ollama, lokal (Feature 025, MVP-407): nativer `/api/chat`-Endpunkt —
 * bewusst NICHT der OpenAI-Kompatibilitätslayer, weil nur der native
 * Endpunkt strukturierte Ausgabe (`format`) sauber unterstützt. Kein
 * Auth (Absicherung via Reverse Proxy, siehe Verbindungs-Hilfe);
 * Preflight über `/api/tags`.
 */
class OllamaProvider extends AbstractLlmProvider {
    protected function baseUrl(): string {
        return rtrim($this->connection->base_url ?: 'http://localhost:11434', '/');
    }

    /** @return array<string, string> */
    protected function headers(): array {
        return [];
    }

    public function preflight(): void {
        $this->getJson('/api/tags');
    }

    protected function complete(string $system, string $user, bool $expectJson = false): Completion {
        $payload = [
            'model' => $this->requireModel(),
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'options' => ['num_predict' => self::MAX_OUTPUT_TOKENS],
        ];

        if ($expectJson) {
            $payload['format'] = 'json';
        }

        $response = $this->postJson('/api/chat', $payload);

        return new Completion((string) $response->json('message.content', ''), new AiUsage(
            inputTokens: (int) $response->json('prompt_eval_count', 0),
            outputTokens: (int) $response->json('eval_count', 0),
        ));
    }

    /**
     * Ollamas JSON-Mode liefert Objekte oder Arrays — beim Objekt die
     * erste Array-Eigenschaft verwenden (Klassifikation).
     *
     * @return list<string>
     */
    protected function parseJsonStringList(string $raw): array {
        $list = parent::parseJsonStringList($raw);
        if ($list !== []) {
            return $list;
        }

        $decoded = json_decode(trim($raw), true);
        if (is_array($decoded)) {
            foreach ($decoded as $value) {
                if (is_array($value)) {
                    return array_values(array_filter(array_map(
                        static fn (mixed $v): string => is_scalar($v) ? (string) $v : '',
                        $value
                    ), static fn (string $v): bool => $v !== ''));
                }
            }
        }

        return [];
    }
}
