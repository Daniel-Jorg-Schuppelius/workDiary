<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FeedProjection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed;

use LogicException;

/**
 * SQL-Bausteine der Feed-Projektion — gemeinsam für Kern- und Plugin-Quellen.
 */
final class FeedProjection {
    /**
     * Spalten der Feed-Zeile. Die Reihenfolge ist der UNION-Vertrag aller
     * {@see DocumentFeedSource}-Implementierungen.
     */
    public const COLUMNS = [
        'source_type', 'source_id', 'link_id', 'origin', 'direction', 'kind',
        'sign', 'number', 'doc_date', 'due_on', 'state', 'is_archived',
        'contact_type', 'contact_id', 'contact_name', 'dunning_level',
        'amount_gross', 'open_amount', 'currency',
    ];

    /**
     * Spaltenliste einer Projektion. Jedes Fragment stammt aus Enums,
     * Modellkonstanten oder festen Spaltennamen — nie aus Eingaben. Nach dem
     * implode kann PHPStan die literal-string-Eigenschaft nicht mehr beweisen
     * (Muster wie in Services/Integration/Match).
     *
     * @param  list<string>  $columns
     * @return literal-string
     */
    public static function columns(array $columns): string {
        // Vertragswächter: eine Quelle mit abweichender Spaltenzahl würde im
        // UNION sonst still Spalten verschieben.
        if (count($columns) !== count(self::COLUMNS)) {
            throw new LogicException('Feed-Projektion braucht genau ' . count(self::COLUMNS) . ' Spalten, ' . count($columns) . ' übergeben.');
        }

        // @phpstan-ignore return.type
        return implode(', ', $columns);
    }

    /**
     * Baut ein `CASE <column> WHEN … END` aus einer Wertetabelle. Alle
     * Schlüssel und Werte stammen aus Enums/Modellkonstanten, nie aus
     * Nutzereingaben.
     *
     * @param  array<string, string>  $map
     */
    public static function caseMap(string $column, array $map, string $default, bool $quoted = true): string {
        $sql = "CASE $column";
        foreach ($map as $when => $then) {
            $value = $quoted ? "'" . $then . "'" : $then;
            $sql .= " WHEN '" . $when . "' THEN " . $value;
        }
        $sql .= ' ELSE ' . ($quoted ? "'" . $default . "'" : $default) . ' END';

        return $sql;
    }

    public static function defaultCurrency(): string {
        $code = (string) config('invoicing.default_currency', 'EUR');

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : 'EUR';
    }
}
