<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParsedBoq.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

/**
 * Format-neutrales Zwischenergebnis eines GAEB-Parsers (Feature 049, MVP-081).
 * Bewusst entkoppelt von Eloquent, damit Parser und Generator perspektivisch in
 * ein Toolkit wandern können (siehe Integrationsprinzip der Feature-Doku).
 *
 * @phpstan-type ParsedSection array{ref: string, parent_ref: ?string, label: ?string, position: int}
 * @phpstan-type ParsedItem array{ref: string, section_ref: ?string, type: string, short_text: ?string, long_text: ?string, quantity: ?string, unit: ?string, unit_price: ?string, total_price: ?string, is_addendum: bool, external_id: ?string, position: int}
 */
final class ParsedBoq {
    /**
     * @param list<ParsedSection> $sections
     * @param list<ParsedItem> $items
     */
    public function __construct(
        public readonly ?string $version,
        public readonly ?string $phase,
        public readonly ?string $projectName,
        public readonly ?string $externalId,
        public readonly array $sections,
        public readonly array $items,
    ) {}

    public function itemCount(): int {
        return count($this->items);
    }

    public function sectionCount(): int {
        return count($this->sections);
    }
}
