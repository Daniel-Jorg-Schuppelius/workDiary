<?php
/*
 * Created on   : Thu Oct 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpSearchQuery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Help;

/**
 * Aufbereitete Suchanfrage der Hilfe (MVP-1079): gefaltete Wörter mit ihren
 * Trefferformen und Gewichten aus der Dokumenthäufigkeit einer Sprache. Gilt
 * für Hilfethemen, Seiten und Aktionen gleichermaßen.
 *
 * @phpstan-type SearchTerm array{word: string, forms: list<string>, weight: float, required: bool, known: bool}
 */
final class HelpSearchQuery {
    /**
     * @param  string  $phrase  Vergleichsform der ganzen Anfrage („2FA" → „2 fa")
     * @param  list<string>  $folded  Inhaltswörter der Anfrage in Reihenfolge, auch unbekannte
     * @param  list<SearchTerm>  $terms  `forms` ist das Wort selbst oder seine Korrekturen; `known` = Treffer im Hilfebestand
     * @param  array<string, string>  $corrections  Wort → eingesetzte Korrektur
     * @param  list<string>  $highlights  Formen für die Hervorhebung im Ausschnitt, längste zuerst
     */
    public function __construct(
        public readonly string $raw,
        public readonly string $locale,
        public readonly string $phrase,
        public readonly array $folded,
        public readonly array $terms,
        public readonly ?string $joined,
        public readonly float $joinedWeight,
        public readonly array $corrections,
        public readonly array $highlights,
    ) {}

    public function isEmpty(): bool {
        return $this->terms === [] && $this->joined === null;
    }

    /**
     * Formen, die ein Hilfethema enthalten muss, um überhaupt Kandidat zu
     * sein: bekannte Pflichtwörter und die zusammengezogene Form.
     *
     * @return list<string>
     */
    public function candidateForms(): array {
        $forms = [];
        foreach ($this->terms as $term) {
            if ($term['known'] && $term['required']) {
                array_push($forms, ...$term['forms']);
            }
        }
        if ($this->joined !== null) {
            $forms[] = $this->joined;
        }

        return array_values(array_unique($forms));
    }

    public function requiredCount(): int {
        return count(array_filter($this->terms, static fn(array $term): bool => $term['required']));
    }

    /** Anfrage mit eingesetzten Korrekturen, null ohne Korrektur. */
    public function correctedText(): ?string {
        if ($this->corrections === []) {
            return null;
        }

        return implode(' ', array_map(fn(string $word): string => $this->corrections[$word] ?? $word, $this->folded));
    }
}
