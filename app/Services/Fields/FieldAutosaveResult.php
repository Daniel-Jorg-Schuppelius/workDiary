<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldAutosaveResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields;

/**
 * Antwort auf ein gespeichertes Feld (Feature 203, MVP-1650): neue Version
 * für die nächste Anfrage der Warteschlange und der Stand je Abschnitt, mit
 * dem der Browser seine nachgeführte Zählung abgleicht.
 *
 * @phpstan-import-type SectionProgress from FieldValues
 */
final readonly class FieldAutosaveResult {
    /** @param  list<SectionProgress>  $sections */
    public function __construct(
        public int $version,
        public array $sections,
        public bool $answered,
    ) {}

    /** @return array{version: int, answered: bool, sections: list<SectionProgress>} */
    public function toArray(): array {
        return ['version' => $this->version, 'answered' => $this->answered, 'sections' => $this->sections];
    }
}
