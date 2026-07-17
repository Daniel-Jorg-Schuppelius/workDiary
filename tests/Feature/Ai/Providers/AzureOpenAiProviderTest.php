<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AzureOpenAiProviderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai\Providers;

use App\Enums\Ai\AiProviderType;
use App\Models\Ai\AiProviderConnection;
use Psr\Http\Message\RequestInterface;

class AzureOpenAiProviderTest extends LlmProviderContractTestCase {
    protected function connection(): AiProviderConnection {
        return AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => AiProviderType::AzureOpenAi,
            'base_url' => 'https://wd-test.openai.azure.com',
            'api_key' => 'azure-key',
            'model' => 'wd-gpt-deployment',
        ]);
    }

    protected function completionUrlPattern(): string {
        return 'https://wd-test.openai.azure.com/openai/v1/chat/completions*';
    }

    protected function preflightUrlPattern(): string {
        return 'https://wd-test.openai.azure.com/openai/v1/models*';
    }

    protected function successBody(string $text): array {
        return [
            'choices' => [['message' => ['role' => 'assistant', 'content' => $text]]],
            'usage' => ['prompt_tokens' => 14, 'completion_tokens' => 5],
        ];
    }

    protected function hasAuthHeader(RequestInterface $request): bool {
        return ($request->getHeader('api-key')[0] ?? '') === 'azure-key';
    }
}
