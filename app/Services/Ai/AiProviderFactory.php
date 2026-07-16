<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiProviderFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Ai\AiProviderConnection;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Exceptions\AiProviderNotImplementedException;

/**
 * Auflösung Verbindung → Provider-Adapter (Feature 025, MVP-399).
 * Austauschpunkt nach dem Muster von PluginHttpFactory: Tests ersetzen
 * die Factory im Container ({@see \Tests\Support\FakeAiProviderFactory}).
 * Die konkreten Adapter folgen in MVP-407–410 und werden hier im
 * `match` verdrahtet; bis dahin wirft jeder Typ
 * {@see AiProviderNotImplementedException} — der Invocation-Service
 * behandelt das wie einen Verbindungsfehler (Health + Fallback-Kette).
 */
class AiProviderFactory {
    public function make(AiProviderConnection $connection): AiProviderInterface {
        // MVP-407/408: anthropic, ollama, openai_compatible, openai,
        //              gemini, azure_openai
        // MVP-409/410: deepl, libretranslate, azure_translator,
        //              google_translate
        throw AiProviderNotImplementedException::forType($connection->provider);
    }
}
