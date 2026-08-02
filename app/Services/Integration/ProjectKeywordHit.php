<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectKeywordHit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\Project;

/**
 * Treffer der Schlüsselwort-Zuordnung (MVP-483) samt Begründung — der
 * auslösende Begriff bleibt so für Logs, Tests und spätere Anzeige greifbar.
 */
final class ProjectKeywordHit {
    public function __construct(
        public readonly Project $project,
        public readonly string $keyword,
        /** true = gepflegtes Synonym, false = aus dem Projektnamen abgeleitet */
        public readonly bool $explicit,
    ) {}
}
