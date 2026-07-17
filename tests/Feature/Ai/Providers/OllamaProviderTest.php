<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OllamaProviderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai\Providers;

use App\Enums\Ai\AiProviderType;
use App\Models\Ai\AiProviderConnection;
use Psr\Http\Message\RequestInterface;

class OllamaProviderTest extends LlmProviderContractTestCase {
    protected function connection(): AiProviderConnection {
        return AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => AiProviderType::Ollama,
            'base_url' => 'http://ollama.intern:11434',
            'api_key' => null,
            'model' => 'gemma3',
        ]);
    }

    protected function completionUrlPattern(): string {
        return 'http://ollama.intern:11434/api/chat*';
    }

    protected function preflightUrlPattern(): string {
        return 'http://ollama.intern:11434/api/tags*';
    }

    protected function successBody(string $text): array {
        return [
            'message' => ['role' => 'assistant', 'content' => $text],
            'prompt_eval_count' => 20,
            'eval_count' => 9,
        ];
    }

    /** Ollama hat keinen Auth-Header — Absicherung via Reverse Proxy. */
    protected function hasAuthHeader(RequestInterface $request): bool {
        return $request->getHeader('Authorization') === [];
    }
}
