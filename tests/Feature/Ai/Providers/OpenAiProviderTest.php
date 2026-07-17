<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenAiProviderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai\Providers;

use App\Enums\Ai\AiProviderType;
use App\Models\Ai\AiProviderConnection;
use Psr\Http\Message\RequestInterface;

class OpenAiProviderTest extends LlmProviderContractTestCase {
    protected function connection(): AiProviderConnection {
        return AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => AiProviderType::OpenAi,
            'api_key' => 'sk-openai',
            'model' => 'gpt-test-1',
        ]);
    }

    protected function completionUrlPattern(): string {
        return 'https://api.openai.com/v1/responses*';
    }

    protected function preflightUrlPattern(): string {
        return 'https://api.openai.com/v1/models*';
    }

    protected function successBody(string $text): array {
        return [
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => $text]],
            ]],
            'usage' => ['input_tokens' => 18, 'output_tokens' => 8],
        ];
    }

    protected function hasAuthHeader(RequestInterface $request): bool {
        return ($request->getHeader('Authorization')[0] ?? '') === 'Bearer sk-openai';
    }
}
