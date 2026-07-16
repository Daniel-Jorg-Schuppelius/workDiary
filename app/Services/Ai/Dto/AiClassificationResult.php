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
}
