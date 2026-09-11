<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DraftResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Draft;

/**
 * Ergebnis eines Entwurfsziels (Feature 152, Review 2026-09-11): die
 * Referenz wird der Perioden-Stempel (`draft_reference`), das Label die
 * angehängte Bemerkung. Ziele, deren Positionen als Bezug taugen (lokaler
 * Rechnungsentwurf), nennen den Morph-Typ und je Periode die Positions-ID;
 * Ziele ohne gespiegelte Entwürfe (Lexoffice) lassen beides leer.
 */
final class DraftResult {
    /**
     * @param  array<int, int>  $morphIds  Perioden-ID → Positions-ID (nur mit `$morphClass`)
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $label,
        public readonly ?string $url,
        public readonly ?string $morphClass,
        public readonly array $morphIds,
        public readonly int $lines,
        public readonly float $net,
    ) {}

    /** Positions-ID zur Periode, wenn das Ziel Bezüge liefert. */
    public function morphIdFor(int $periodId): ?int {
        if ($this->morphClass === null) {
            return null;
        }

        return $this->morphIds[$periodId] ?? null;
    }
}
