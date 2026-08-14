<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExtractRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
