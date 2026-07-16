<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlossaryEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

/**
 * Glossar-Begriff aus dem KI-Gedächtnis (Feature 025): Begriff →
 * Bedeutung/gewünschte Ausschreibung, optional mit Zielübersetzung für
 * das Verb Übersetzen. Das Gedächtnis-Datenmodell folgt in MVP-401;
 * dieses DTO ist der neutrale Transport in die Provider-Aufrufe.
 */
final class GlossaryEntry {
    public function __construct(
        public readonly string $term,
        public readonly string $meaning,
        public readonly ?string $translation = null,
    ) {}

    /** @return array{term: string, meaning: string, translation: ?string} */
    public function toArray(): array {
        return [
            'term' => $this->term,
            'meaning' => $this->meaning,
            'translation' => $this->translation,
        ];
    }
}
