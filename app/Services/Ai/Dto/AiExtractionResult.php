<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiExtractionResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

/**
 * Ergebnis des Extrahieren-Verbs (Feature 088): Feldwerte (null = im
 * Beleg nicht gefunden) plus Konfidenz je Feld (0–100). Muss wie alle
 * Ergebnis-DTOs cache-serialisierbar bleiben (nur Skalare/Arrays).
 */
final class AiExtractionResult {
    /**
     * @param array<string, string|null> $values
     * @param array<string, int> $confidence
     */
    public function __construct(
        public readonly array $values,
        public readonly array $confidence,
        public readonly AiUsage $usage,
    ) {}

    /**
     * Nur Felder aus dem angeforderten Schema — Adapter-Halluzinationen kappen.
     *
     * @param  array<string, string>  $schema
     */
    public function onlyFromSchema(array $schema): self {
        $allowed = array_fill_keys(array_keys($schema), true);

        return new self(
            array_intersect_key($this->values, $allowed),
            array_intersect_key($this->confidence, $allowed),
            $this->usage,
        );
    }

    /** Wert nur, wenn vorhanden und die Konfidenz die Schwelle erreicht. */
    public function confidentValue(string $field, int $minConfidence = 60): ?string {
        $value = $this->values[$field] ?? null;
        if ($value === null || trim($value) === '') {
            return null;
        }

        return ($this->confidence[$field] ?? 0) >= $minConfidence ? trim($value) : null;
    }
}
