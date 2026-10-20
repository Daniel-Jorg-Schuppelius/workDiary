<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiClassificationResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

/**
 * Klassifikationsergebnis (MVP-398). `onlyFromCatalog()` ist die
 * Vertragsgarantie: Werte außerhalb des erlaubten Katalogs werden
 * verworfen, nie durchgereicht — kein frei erfundenes Label erreicht
 * die Fachlogik.
 */
final class AiClassificationResult {
    /** @param list<string> $values */
    public function __construct(
        public readonly array $values,
        public readonly AiUsage $usage = new AiUsage(),
    ) {}

    /** @param list<string> $catalog */
    public function onlyFromCatalog(array $catalog): self {
        $filtered = array_values(array_intersect($this->values, $catalog));

        return $filtered === $this->values
            ? $this
            : new self($filtered, $this->usage);
    }

    /** @return array<string, mixed> Cache-Form: der Cache hält nur Arrays, keine Objekte (CLAUDE.md „Cache“) */
    public function toArray(): array {
        return ['values' => $this->values, 'usage' => $this->usage->toArray()];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self {
        return new self(array_values(array_map('strval', (array) ($data['values'] ?? []))), AiUsage::fromArray((array) ($data['usage'] ?? [])));
    }
}
