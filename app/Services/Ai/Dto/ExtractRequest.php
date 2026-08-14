<?php

declare(strict_types=1);

namespace App\Services\Ai\Dto;

use App\Enums\Ai\AiVerb;
use App\Services\Ai\Contracts\AiRequestInterface;
use App\Services\Ai\Dto\Concerns\HashesPayload;

/**
 * Verb „Extrahieren" (Feature 088): Belegtext → Werte für ein festes
 * Zielschema. Das Schema ist abschließend — Adapter liefern ausschließlich
 * die angeforderten Felder (Structured Output) mit Konfidenz je Feld;
 * fehlende Angaben bleiben null und werden nie erfunden.
 */
final class ExtractRequest implements AiRequestInterface {
    use HashesPayload;

    /**
     * @param array<string, string> $schema Feldname → Beschreibung/Formatvorgabe
     */
    public function __construct(
        public readonly string $text,
        public readonly array $schema,
        public readonly string $language = 'de',
    ) {}

    public function verb(): AiVerb {
        return AiVerb::Extract;
    }

    public function fingerprint(): string {
        return $this->hashPayload([
            'verb' => $this->verb()->value,
            'text' => $this->text,
            'schema' => $this->schema,
            'language' => $this->language,
        ]);
    }

    public function estimatedUnits(): int {
        return $this->estimateTokens($this->text, implode(' ', array_keys($this->schema)));
    }
}
