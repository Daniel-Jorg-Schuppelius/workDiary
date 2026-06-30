<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchStrategy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Match;

use Illuminate\Database\Eloquent\Builder;

/**
 * Eine einzelne Abgleich-Strategie innerhalb eines {@see MatchProfile} (z. B.
 * „gleiche USt-IdNr." oder „Firma+PLZ identisch"). Strategien arbeiten auf einem
 * gemappten Wertesatz (lokales Feldschema) und liefern zwei Wege:
 *
 *  - {@see query()}: gezielte DB-Suche lokaler Kandidaten zu einem Remote-Satz
 *    (1:n) — für den {@see \App\Services\Integration\IntegrationResolver}.
 *  - {@see matches()}: paarweiser In-Memory-Vergleich zweier Wertesätze — für
 *    den paarweisen Dubletten-Finder.
 *
 * Confidence-Stufen: exact > likely > fuzzy.
 */
abstract class MatchStrategy {
    public const EXACT = 'exact';
    public const LIKELY = 'likely';
    public const FUZZY = 'fuzzy';

    public function __construct(
        public readonly string $confidence,
        public readonly string $reason,
    ) {}

    /**
     * Engt die Basis-Query auf Kandidaten ein, die nach dieser Strategie zum
     * gemappten Wertesatz passen. Liefert null, wenn die Strategie auf diesen
     * Satz nicht anwendbar ist (z. B. relevantes Feld leer) oder nicht
     * query-fähig ist (Fuzzy) — dann wird sie beim 1:n-Lookup übersprungen.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $base
     * @param  array<string, mixed>  $fields
     * @return Builder<\Illuminate\Database\Eloquent\Model>|null
     */
    abstract public function query(Builder $base, array $fields): ?Builder;

    /**
     * Paarweiser Vergleich zweier gemappter Wertesätze.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    abstract public function matches(array $a, array $b): bool;

    /**
     * Lokale Feldnamen, die diese Strategie liest (zum Extrahieren aus Modellen).
     *
     * @return list<string>
     */
    abstract public function fields(): array;
}
