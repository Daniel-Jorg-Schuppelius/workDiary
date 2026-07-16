<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TranslateRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

use App\Enums\Ai\AiVerb;
use App\Services\Ai\Contracts\AiRequestInterface;
use App\Services\Ai\Dto\Concerns\HashesPayload;

/**
 * Verb „Übersetzen" (Feature 025, MVP-409/410): Text → Zielsprache mit
 * zu erzwingenden Glossarbegriffen aus dem KI-Gedächtnis. Dedizierte
 * MT-Adapter setzen die Begriffe deterministisch um (DeepL-Glossar,
 * Azure Dynamic Dictionary); LLM-Adapter nur probabilistisch — das
 * Ergebnis trägt diese Unterscheidung
 * ({@see AiTranslationResult::$deterministicTerminology}).
 */
final class TranslateRequest implements AiRequestInterface {
    use HashesPayload;

    /**
     * @param list<GlossaryEntry> $glossary Einträge mit Zielübersetzung
     * @param 'text'|'html' $format
     * @param 'default'|'more'|'less' $formality Anlehnung an DeepL-`formality`
     */
    public function __construct(
        public readonly string $text,
        public readonly string $targetLanguage,
        public readonly ?string $sourceLanguage = null,
        public readonly string $format = 'text',
        public readonly string $formality = 'default',
        public readonly array $glossary = [],
    ) {}

    public function verb(): AiVerb {
        return AiVerb::Translate;
    }

    public function fingerprint(): string {
        return $this->hashPayload([
            'verb' => $this->verb()->value,
            'text' => $this->text,
            'target' => $this->targetLanguage,
            'source' => $this->sourceLanguage,
            'format' => $this->format,
            'formality' => $this->formality,
            'glossary' => array_map(static fn (GlossaryEntry $e): array => $e->toArray(), $this->glossary),
        ]);
    }

    /** Übersetzungs-Budget zählt Zeichen (DeepL-/Azure-Preismodell). */
    public function estimatedUnits(): int {
        return max(1, mb_strlen($this->text));
    }
}
