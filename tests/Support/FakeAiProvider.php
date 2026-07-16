<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeAiProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Ai\Contracts\{LlmProviderInterface, TranslationProviderInterface};
use App\Services\Ai\Dto\{AiClassificationResult, AiFindResult, AiTextResult, AiTranslationResult, AiUsage, ClassifyRequest, ExplainRequest, FindRequest, FormulateRequest, SummarizeRequest, TranslateRequest};

/**
 * Fake-Adapter beider Familien für Feature-Tests (MVP-399): zeichnet
 * alle Aufrufe auf und liefert deterministische Antworten. Verhalten
 * pro Test über die öffentlichen Eigenschaften steuerbar; installiert
 * wird er über {@see FakeAiProviderFactory::install()}.
 */
class FakeAiProvider implements LlmProviderInterface, TranslationProviderInterface {
    /** @var list<array{method: string, request: object}> */
    public array $calls = [];

    public string $textResponse = 'Fake-Antwort';

    /** @var list<string> */
    public array $classificationResponse = [];

    /** @var list<string> */
    public array $findResponse = [];

    public AiUsage $usage;

    public function __construct() {
        $this->usage = new AiUsage(inputTokens: 10, outputTokens: 5, characters: 42);
    }

    public function preflight(): void {
        $this->calls[] = ['method' => 'preflight', 'request' => new \stdClass()];
    }

    public function formulate(FormulateRequest $request): AiTextResult {
        $this->calls[] = ['method' => 'formulate', 'request' => $request];

        return new AiTextResult($this->textResponse, $this->usage);
    }

    public function summarize(SummarizeRequest $request): AiTextResult {
        $this->calls[] = ['method' => 'summarize', 'request' => $request];

        return new AiTextResult($this->textResponse, $this->usage);
    }

    public function classify(ClassifyRequest $request): AiClassificationResult {
        $this->calls[] = ['method' => 'classify', 'request' => $request];

        return new AiClassificationResult($this->classificationResponse, $this->usage);
    }

    public function explain(ExplainRequest $request): AiTextResult {
        $this->calls[] = ['method' => 'explain', 'request' => $request];

        return new AiTextResult($this->textResponse, $this->usage);
    }

    public function find(FindRequest $request): AiFindResult {
        $this->calls[] = ['method' => 'find', 'request' => $request];

        return new AiFindResult($this->findResponse, $this->usage);
    }

    public function translate(TranslateRequest $request): AiTranslationResult {
        $this->calls[] = ['method' => 'translate', 'request' => $request];

        return new AiTranslationResult($this->textResponse, deterministicTerminology: true, usage: $this->usage);
    }

    public function callCount(?string $method = null): int {
        if ($method === null) {
            return count($this->calls);
        }

        return count(array_filter($this->calls, static fn (array $c): bool => $c['method'] === $method));
    }
}
