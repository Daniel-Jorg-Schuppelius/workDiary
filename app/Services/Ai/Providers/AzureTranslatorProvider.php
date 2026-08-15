<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AzureTranslatorProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use GuzzleHttp\Client as GuzzleClient;
use TranslationToolkit\Providers\{AbstractHttpTranslationProvider, AzureTranslatorProvider as ToolkitAzure};

/**
 * Azure Translator (Feature 025, MVP-410) über das php-translation-toolkit:
 * `POST /translate` (v3.0) mit `Ocp-Apim-Subscription-Key` + Region
 * (`options.region`). Terminologie deterministisch über
 * Dynamic-Dictionary-Markup je Request direkt aus dem KI-Gedächtnis — kein
 * Glossar-Sync nötig. „No-Trace": Texte werden laut Microsoft nicht
 * persistiert.
 */
class AzureTranslatorProvider extends AbstractTranslationAdapter {
    protected function baseUrl(): string {
        return rtrim($this->connection->base_url ?: 'https://api.cognitive.microsofttranslator.com', '/');
    }

    protected function makeProvider(?GuzzleClient $transport): AbstractHttpTranslationProvider {
        $region = (string) data_get($this->connection->options, 'region', '');

        return new ToolkitAzure(
            apiKey: $this->requireApiKey(),
            region: $region !== '' ? $region : null,
            baseUrl: $this->baseUrl(),
            httpClient: $transport,
        );
    }
}
