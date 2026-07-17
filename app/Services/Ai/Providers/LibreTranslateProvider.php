<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LibreTranslateProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\TranslationProviderInterface;
use App\Services\Ai\Dto\{AiTranslationResult, AiUsage, GlossaryEntry, TranslateRequest};

/**
 * LibreTranslate, selbst gehostet (Feature 025, MVP-410): On-Premise-
 * Übersetzung ohne natives Glossar. Terminologie wird app-seitig über
 * Platzhalter erzwungen: Glossarbegriffe werden vor der Übersetzung
 * durch stabile Token ersetzt und danach durch die Zielübersetzung —
 * dadurch deterministisch. Qualitätshinweis (unter DeepL-Niveau,
 * Pivot über Englisch) steht in der Verbindungs-Hilfe.
 */
class LibreTranslateProvider extends AbstractHttpAiProvider implements TranslationProviderInterface {
    protected function baseUrl(): string {
        return rtrim($this->connection->base_url ?: 'http://localhost:5000', '/');
    }

    /** @return array<string, string> */
    protected function headers(): array {
        return [];
    }

    public function preflight(): void {
        $this->getJson('/languages');
    }

    public function translate(TranslateRequest $request): AiTranslationResult {
        $entries = array_values(array_filter(
            $request->glossary,
            static fn (GlossaryEntry $e): bool => filled($e->translation)
        ));

        [$prepared, $tokens] = $this->maskTerms($request->text, $entries);

        $payload = [
            'q' => $prepared,
            'source' => strtolower($request->sourceLanguage ?? 'auto'),
            'target' => strtolower($request->targetLanguage),
            'format' => $request->format === 'html' ? 'html' : 'text',
        ];
        if (filled($this->connection->api_key)) {
            $payload['api_key'] = (string) $this->connection->api_key;
        }

        $response = $this->postJson('/translate', $payload);

        $translated = (string) $response->json('translatedText', '');
        foreach ($tokens as $token => $translation) {
            $translated = str_replace($token, $translation, $translated);
        }

        return new AiTranslationResult(
            text: $translated,
            deterministicTerminology: $tokens !== [],
            detectedSourceLanguage: $response->json('detectedLanguage.language'),
            usage: new AiUsage(characters: mb_strlen($request->text)),
        );
    }

    /**
     * Glossarbegriffe durch übersetzungsstabile Token ersetzen.
     *
     * @param list<GlossaryEntry> $entries
     * @return array{0: string, 1: array<string, string>} [präparierter Text, Token → Zielübersetzung]
     */
    private function maskTerms(string $text, array $entries): array {
        $tokens = [];
        foreach ($entries as $i => $entry) {
            $token = sprintf('WDTERM%dX', $i);
            $masked = preg_replace(
                '/' . preg_quote($entry->term, '/') . '/iu',
                $token,
                $text,
                -1,
                $count
            );
            if ($masked !== null && $count > 0) {
                $text = $masked;
                $tokens[$token] = (string) $entry->translation;
            }
        }

        return [$text, $tokens];
    }
}
