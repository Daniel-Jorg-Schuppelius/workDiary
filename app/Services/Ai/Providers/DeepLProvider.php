<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeepLProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\TranslationProviderInterface;
use App\Services\Ai\Dto\{AiTranslationResult, AiUsage, GlossaryEntry, TranslateRequest};
use App\Services\Ai\Exceptions\AiProviderCallException;

/**
 * DeepL API Pro (Feature 025, MVP-409): `POST /v2/translate` mit
 * `formality`/`tag_handling` und deterministischer Glossar-Erzwingung
 * über multilinguale v3-Glossare, abgeleitet aus dem KI-Gedächtnis
 * (führende Quelle bleibt das Gedächtnis; das DeepL-Glossar wird je
 * Aufruf idempotent per Voll-Ersetzung des Sprachpaar-Dictionaries
 * nachgeführt — damit wirkt auch die Löschkaskade automatisch).
 * Free-Tarif ist gesperrt (Schlüssel-Suffix `:fx`) — er nutzt Eingaben
 * zur Modellverbesserung.
 */
class DeepLProvider extends AbstractHttpAiProvider implements TranslationProviderInterface {
    private const DEFAULT_SOURCE_LANG = 'de';

    protected function baseUrl(): string {
        return rtrim($this->connection->base_url ?: 'https://api.deepl.com', '/');
    }

    /** @return array<string, string> */
    protected function headers(): array {
        $key = $this->requireApiKey();

        if (str_ends_with($key, ':fx')) {
            // Feature 025, Leitprinzip 4: Tarife mit Trainingsnutzung
            // sind technisch nicht anbindbar.
            throw AiProviderCallException::transport('deepl', 'DeepL-Free-Schlüssel (…:fx) sind nicht zulässig — API Pro erforderlich.');
        }

        return ['Authorization' => 'DeepL-Auth-Key ' . $key];
    }

    public function preflight(): void {
        $this->getJson('/v2/usage');
    }

    public function translate(TranslateRequest $request): AiTranslationResult {
        $source = strtoupper($request->sourceLanguage ?? self::DEFAULT_SOURCE_LANG);
        $target = strtoupper($request->targetLanguage);

        $glossaryId = $this->ensureGlossary($source, $target, $request->glossary);

        $payload = [
            'text' => [$request->text],
            'target_lang' => $target,
            'source_lang' => $source,
        ];
        if ($request->format === 'html') {
            $payload['tag_handling'] = 'html';
        }
        if ($request->formality === 'more' || $request->formality === 'less') {
            $payload['formality'] = 'prefer_' . $request->formality;
        }
        if ($glossaryId !== null) {
            $payload['glossary_id'] = $glossaryId;
        }

        $response = $this->postJson('/v2/translate', $payload);

        return new AiTranslationResult(
            text: (string) $response->json('translations.0.text', ''),
            deterministicTerminology: $glossaryId !== null,
            detectedSourceLanguage: $response->json('translations.0.detected_source_language'),
            usage: new AiUsage(characters: mb_strlen($request->text)),
        );
    }

    /**
     * Idempotenter Glossar-Sync (v3, multilingual): ein Glossar je
     * Verbindung, Dictionary je Sprachpaar wird voll ersetzt. Die
     * Glossar-ID wird an der Verbindung gemerkt.
     *
     * @param list<GlossaryEntry> $glossary
     */
    private function ensureGlossary(string $source, string $target, array $glossary): ?string {
        $entries = array_filter($glossary, static fn (GlossaryEntry $e): bool => filled($e->translation));
        if ($entries === []) {
            return null;
        }

        $tsv = implode("\n", array_map(
            static fn (GlossaryEntry $e): string => str_replace(["\t", "\n"], ' ', $e->term) . "\t" . str_replace(["\t", "\n"], ' ', (string) $e->translation),
            array_values($entries)
        ));

        $dictionary = [
            'source_lang' => $source,
            'target_lang' => $target,
            'entries' => $tsv,
            'entries_format' => 'tsv',
        ];

        $options = (array) ($this->connection->options ?? []);
        $glossaryId = $options['deepl_glossary_id'] ?? null;

        if (is_string($glossaryId) && $glossaryId !== '') {
            // Dictionary des Sprachpaars voll ersetzen (idempotent).
            try {
                $response = $this->api()->putJson($this->url('/v3/glossaries/' . $glossaryId . '/dictionaries'), $dictionary);
                if ($response->status() < 400) {
                    return $glossaryId;
                }
                // Glossar remote verschwunden (404 o. ä.) → neu anlegen.
            } catch (\Throwable) {
                // Transportfehler → Neuanlage versuchen.
            }
        }

        $response = $this->postJson('/v3/glossaries', [
            'name' => 'workdiary-org-' . (int) $this->connection->organization_id,
            'dictionaries' => [$dictionary],
        ]);

        $glossaryId = (string) $response->json('glossary_id', '');
        if ($glossaryId === '') {
            throw AiProviderCallException::transport('deepl', 'Glossar-Anlage ohne glossary_id beantwortet.');
        }

        $options['deepl_glossary_id'] = $glossaryId;
        $this->connection->forceFill(['options' => $options])->save();

        return $glossaryId;
    }
}
