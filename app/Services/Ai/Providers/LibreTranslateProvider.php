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

use GuzzleHttp\Client as GuzzleClient;
use TranslationToolkit\Providers\{AbstractHttpTranslationProvider, LibreTranslateProvider as ToolkitLibre};

/**
 * LibreTranslate, selbst gehostet (Feature 025, MVP-410) über das
 * php-translation-toolkit: On-Premise-Übersetzung ohne natives Glossar.
 * Terminologie wird über Token-Maskierung erzwungen — Glossarbegriffe gehen
 * als stabile Token raus und kommen als Zielübersetzung zurück. Der
 * Qualitätshinweis (unter DeepL-Niveau, Pivot über Englisch) steht in der
 * Verbindungs-Hilfe. Der API-Schlüssel ist optional.
 */
class LibreTranslateProvider extends AbstractTranslationAdapter {
    protected function baseUrl(): string {
        return rtrim($this->connection->base_url ?: 'http://localhost:5000', '/');
    }

    protected function makeProvider(?GuzzleClient $transport): AbstractHttpTranslationProvider {
        $key = (string) $this->connection->api_key;

        return new ToolkitLibre(
            baseUrl: $this->baseUrl(),
            apiKey: $key !== '' ? $key : null,
            httpClient: $transport,
        );
    }
}
