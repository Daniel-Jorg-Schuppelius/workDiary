<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AnthropicProviderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai\Providers;

use App\Enums\Ai\AiProviderType;
use App\Models\Ai\AiProviderConnection;
use Psr\Http\Message\RequestInterface;

class AnthropicProviderTest extends LlmProviderContractTestCase {
    protected function connection(): AiProviderConnection {
        return AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => AiProviderType::Anthropic,
            'api_key' => 'sk-ant-test',
            'model' => 'claude-opus-4-8',
        ]);
    }

    protected function completionUrlPattern(): string {
        return 'https://api.anthropic.com/v1/messages*';
    }

    protected function preflightUrlPattern(): string {
        return 'https://api.anthropic.com/v1/models*';
    }

    protected function successBody(string $text): array {
        return [
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 12, 'output_tokens' => 7],
        ];
    }

    protected function hasAuthHeader(RequestInterface $request): bool {
        return ($request->getHeader('x-api-key')[0] ?? '') === 'sk-ant-test'
            && ($request->getHeader('anthropic-version')[0] ?? '') !== '';
    }
}
