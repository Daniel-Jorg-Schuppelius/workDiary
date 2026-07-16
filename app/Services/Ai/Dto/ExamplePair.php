<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExamplePair.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

/**
 * Beispielpaar (Rohtext → gewünschter Zieltext) aus dem KI-Gedächtnis —
 * die wirksamste Anpassungsform für Formulieren/Zusammenfassen
 * (Few-Shot, Feature 025).
 */
final class ExamplePair {
    public function __construct(
        public readonly string $source,
        public readonly string $target,
    ) {}

    /** @return array{source: string, target: string} */
    public function toArray(): array {
        return ['source' => $this->source, 'target' => $this->target];
    }
}
