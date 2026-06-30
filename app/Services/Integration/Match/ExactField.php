<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExactField.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Match;

use Illuminate\Database\Eloquent\Builder;

/**
 * Treffer bei identischem (normalisiertem) Einzelfeld — z. B. USt-IdNr.,
 * Kontaktnummer, E-Mail. Vergleich case-/whitespace-insensitiv.
 */
class ExactField extends MatchStrategy {
    public function __construct(
        public readonly string $field,
        string $confidence = MatchStrategy::EXACT,
        ?string $reason = null,
    ) {
        parent::__construct($confidence, $reason ?? $field);
    }

    public function query(Builder $base, array $fields): ?Builder {
        $value = Normalize::id($fields[$this->field] ?? null);
        if ($value === '') {
            return null;
        }

        // Normalisierter Vergleich auf DB-Ebene (Trim/Lower/ohne Spaces), damit
        // Groß-/Kleinschreibung und Leerzeichen keine Treffer verhindern.
        // replace/lower/trim sind in SQLite (Dev) wie MySQL (Prod) vorhanden.
        $column = $this->safeColumn();

        return $base->whereRaw("replace(lower(trim($column)), ' ', '') = ?", [$value]);
    }

    public function matches(array $a, array $b): bool {
        $va = Normalize::id($a[$this->field] ?? null);
        $vb = Normalize::id($b[$this->field] ?? null);

        return $va !== '' && $va === $vb;
    }

    public function fields(): array {
        return [$this->field];
    }

    /**
     * Bereinigter, unquotierter Spaltenname. Feldnamen stammen aus dem
     * MatchProfile (entwickler-kontrolliert) und sind einfache snake_case-
     * Bezeichner — kein Identifier-Quoting (DB-portabel), kein Injection-Risiko.
     */
    private function safeColumn(): string {
        return (string) preg_replace('/[^a-z0-9_]/', '', mb_strtolower($this->field));
    }
}
