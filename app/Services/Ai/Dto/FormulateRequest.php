<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormulateRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

use App\Enums\Ai\AiVerb;
use App\Services\Ai\Contracts\AiRequestInterface;
use App\Services\Ai\Dto\Concerns\HashesPayload;

/**
 * Verb „Formulieren" (Feature 025): Stichworte → sauberer Text. Der
 * Provider darf ausschließlich umformulieren, was im Input steht —
 * keine erfundenen Leistungen, Mengen oder Fakten; diese Regel ist Teil
 * des Prompt-Vertrags jedes Adapters. Stil, Glossar und Beispielpaare
 * kommen aus dem KI-Gedächtnis (MVP-401).
 */
final class FormulateRequest implements AiRequestInterface {
    use HashesPayload;

    /**
     * @param list<string> $styleRules
     * @param list<GlossaryEntry> $glossary
     * @param list<ExamplePair> $examples
     * @param list<string> $contextHints z. B. Tätigkeitsart, Zeitraum — nie Preise/Personendaten
     */
    public function __construct(
        public readonly string $text,
        public readonly string $language = 'de',
        public readonly array $styleRules = [],
        public readonly array $glossary = [],
        public readonly array $examples = [],
        public readonly array $contextHints = [],
    ) {}

    public function verb(): AiVerb {
        return AiVerb::Formulate;
    }

    public function fingerprint(): string {
        return $this->hashPayload([
            'verb' => $this->verb()->value,
            'text' => $this->text,
            'language' => $this->language,
            'style' => $this->styleRules,
            'glossary' => array_map(static fn (GlossaryEntry $e): array => $e->toArray(), $this->glossary),
            'examples' => array_map(static fn (ExamplePair $e): array => $e->toArray(), $this->examples),
            'hints' => $this->contextHints,
        ]);
    }

    public function estimatedUnits(): int {
        return $this->estimateTokens($this->text, implode(' ', $this->styleRules));
    }
}
