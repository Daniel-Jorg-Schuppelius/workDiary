<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeminiProviderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai\Providers;

use App\Enums\Ai\AiProviderType;
use App\Models\Ai\AiProviderConnection;
use Psr\Http\Message\RequestInterface;

class GeminiProviderTest extends LlmProviderContractTestCase {
    protected function connection(): AiProviderConnection {
        return AiProviderConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => AiProviderType::Gemini,
            'api_key' => 'g-key',
            'model' => 'gemini-test',
        ]);
    }

    protected function completionUrlPattern(): string {
        return 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent*';
    }

    protected function preflightUrlPattern(): string {
        return 'https://generativelanguage.googleapis.com/v1beta/models?*';
    }

    protected function successBody(string $text): array {
        return [
            'candidates' => [['content' => ['parts' => [['text' => $text]]]]],
            'usageMetadata' => ['promptTokenCount' => 22, 'candidatesTokenCount' => 11],
        ];
    }

    protected function hasAuthHeader(RequestInterface $request): bool {
        return ($request->getHeader('x-goog-api-key')[0] ?? '') === 'g-key';
    }
}
