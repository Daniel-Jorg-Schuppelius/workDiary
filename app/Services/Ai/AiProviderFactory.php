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

use App\Enums\Ai\AiProviderType;
use App\Models\Ai\AiProviderConnection;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Exceptions\AiProviderNotImplementedException;
use App\Services\Ai\Providers\{AnthropicProvider, AzureOpenAiProvider, AzureTranslatorProvider, DeepLProvider, GeminiProvider, LibreTranslateProvider, OllamaProvider, OpenAiCompatibleProvider, OpenAiProvider};

/**
 * Auflösung Verbindung → Provider-Adapter (Feature 025, MVP-399/407-410).
 * Austauschpunkt nach dem Muster von PluginHttpFactory: Tests ersetzen
 * die Factory im Container ({@see \Tests\Support\FakeAiProviderFactory}).
 * `Fake` und `GoogleTranslate` haben bewusst keinen produktiven Adapter
 * (Tests bzw. spätere Ausbaustufe).
 */
class AiProviderFactory {
    public function make(AiProviderConnection $connection): AiProviderInterface {
        return match ($connection->provider) {
            AiProviderType::Anthropic => new AnthropicProvider($connection),
            AiProviderType::Ollama => new OllamaProvider($connection),
            AiProviderType::OpenAiCompatible => new OpenAiCompatibleProvider($connection),
            AiProviderType::OpenAi => new OpenAiProvider($connection),
            AiProviderType::Gemini => new GeminiProvider($connection),
            AiProviderType::AzureOpenAi => new AzureOpenAiProvider($connection),
            AiProviderType::DeepL => new DeepLProvider($connection),
            AiProviderType::LibreTranslate => new LibreTranslateProvider($connection),
            AiProviderType::AzureTranslator => new AzureTranslatorProvider($connection),
            AiProviderType::GoogleTranslate,
            AiProviderType::Fake => throw AiProviderNotImplementedException::forType($connection->provider),
        };
    }
}
