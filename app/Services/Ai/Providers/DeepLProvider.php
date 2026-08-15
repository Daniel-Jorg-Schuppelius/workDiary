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

use App\Services\Ai\Dto\TranslateRequest;
use App\Services\Ai\Exceptions\AiProviderCallException;
use App\Services\Ai\Support\ConnectionGlossaryIdStore;
use GuzzleHttp\Client as GuzzleClient;
use TranslationToolkit\Providers\{AbstractHttpTranslationProvider, DeepLProvider as ToolkitDeepL};

/**
 * DeepL API Pro (Feature 025, MVP-409) über das php-translation-toolkit:
 * `POST /v2/translate` mit `formality`/`tag_handling` und deterministischer
 * Glossar-Erzwingung über multilinguale v3-Glossare, abgeleitet aus dem
 * KI-Gedächtnis (führende Quelle bleibt das Gedächtnis; das DeepL-Glossar
 * wird je Aufruf idempotent per Voll-Ersetzung des Sprachpaar-Dictionaries
 * nachgeführt — damit wirkt auch die Löschkaskade automatisch).
 *
 * Free-Tarif ist gesperrt (Schlüssel-Suffix `:fx`) — er nutzt Eingaben zur
 * Modellverbesserung. Das ist eine Geschäftsregel dieser App (Leitprinzip 4);
 * das Toolkit selbst erlaubt Free-Keys.
 */
class DeepLProvider extends AbstractTranslationAdapter {
    /**
     * DeepL-Glossare brauchen ein Sprachpaar. Ohne Angabe an der Anfrage
     * gilt Deutsch als Quellsprache, sonst bliebe das Glossar wirkungslos.
     */
    private const DEFAULT_SOURCE_LANG = 'de';

    protected function baseUrl(): string {
        return rtrim($this->connection->base_url ?: 'https://api.deepl.com', '/');
    }

    protected function makeProvider(?GuzzleClient $transport): AbstractHttpTranslationProvider {
        $key = $this->requireApiKey();

        if (str_ends_with($key, ':fx')) {
            throw AiProviderCallException::transport($this->providerName(), (string) __('ai.error.deepl_free_key'));
        }

        return new ToolkitDeepL(
            apiKey: $key,
            baseUrl: $this->baseUrl(),
            httpClient: $transport,
            glossaryIdStore: new ConnectionGlossaryIdStore($this->connection),
            glossaryName: 'workdiary-org-' . (int) $this->connection->organization_id,
        );
    }

    protected function sourceLanguage(TranslateRequest $request): ?string {
        return $request->sourceLanguage ?? self::DEFAULT_SOURCE_LANG;
    }
}
