<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiTranslationResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

/**
 * Übersetzungsergebnis (MVP-398). `deterministicTerminology` trennt
 * echte Glossar-Erzwingung (DeepL/Azure Dynamic Dictionary) von
 * probabilistischer LLM-Terminologie — die UI kennzeichnet Letzteres
 * als „Terminologie nicht garantiert" (Feature 025).
 */
final class AiTranslationResult {
    public function __construct(
        public readonly string $text,
        public readonly bool $deterministicTerminology,
        public readonly ?string $detectedSourceLanguage = null,
        public readonly AiUsage $usage = new AiUsage(),
    ) {}

    /** @return array<string, mixed> Cache-Form: der Cache hält nur Arrays, keine Objekte (CLAUDE.md „Cache“) */
    public function toArray(): array {
        return [
            'text' => $this->text,
            'deterministic_terminology' => $this->deterministicTerminology,
            'detected_source_language' => $this->detectedSourceLanguage,
            'usage' => $this->usage->toArray(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self {
        return new self(
            (string) ($data['text'] ?? ''),
            (bool) ($data['deterministic_terminology'] ?? false),
            isset($data['detected_source_language']) ? (string) $data['detected_source_language'] : null,
            AiUsage::fromArray((array) ($data['usage'] ?? [])),
        );
    }
}
