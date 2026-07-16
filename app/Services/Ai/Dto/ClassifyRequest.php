<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassifyRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

use App\Enums\Ai\AiVerb;
use App\Services\Ai\Contracts\AiRequestInterface;
use App\Services\Ai\Dto\Concerns\HashesPayload;

/**
 * Verb „Klassifizieren/Extrahieren" (Feature 025): Freitext →
 * Katalogwerte. Der Katalog ist abschließend — Adapter dürfen
 * ausschließlich Werte aus `catalog` liefern (Structured Output),
 * nie frei erfundene Labels; das Ergebnis wird zusätzlich im
 * {@see \App\Services\Ai\Dto\AiClassificationResult} gegen den Katalog
 * validiert.
 */
final class ClassifyRequest implements AiRequestInterface {
    use HashesPayload;

    /**
     * @param list<string> $catalog erlaubte Werte (Klassifikations-Codes, Tags)
     */
    public function __construct(
        public readonly string $text,
        public readonly array $catalog,
        public readonly bool $multiple = false,
        public readonly string $language = 'de',
    ) {}

    public function verb(): AiVerb {
        return AiVerb::Classify;
    }

    public function fingerprint(): string {
        return $this->hashPayload([
            'verb' => $this->verb()->value,
            'text' => $this->text,
            'catalog' => $this->catalog,
            'multiple' => $this->multiple,
            'language' => $this->language,
        ]);
    }

    public function estimatedUnits(): int {
        return $this->estimateTokens($this->text, implode(' ', $this->catalog));
    }
}
