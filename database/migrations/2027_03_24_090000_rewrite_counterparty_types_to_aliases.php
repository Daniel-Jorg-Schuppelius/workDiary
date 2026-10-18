<?php
/*
 * Created on   : Sun Oct 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_24_090000_rewrite_counterparty_types_to_aliases.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Support\MorphMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Gegenpartei-Typ der Buchungszeilen und offenen Posten als Morph-Alias
 * (MVP-860). Die Buchungsadapter für Ausgangsrechnungen und Auslagen hatten
 * den Klassennamen (`App\Models\Customer\Customer`, `App\Models\Platform\User`)
 * übergeben; Laravel löste ihn bis 13.36 stillschweigend auf, seit 13.37
 * wirft jedes Lesen `ClassMorphViolationException` (Offene Posten,
 * Liquiditätsvorschau). Umgeschrieben wird jeder Wert, der sich über die
 * Morph-Map auf eine Modellklasse mit Alias auflösen lässt — Aliase bleiben,
 * Fremdwerte bleiben. Keine Hash-Kette hängt an diesen Spalten
 * (`accounting_events` führt die Kette), der fachliche Bezug bleibt gleich.
 */
return new class extends Migration {
    /** @var list<array{0: string, 1: string}> Tabelle, Typspalte */
    private const COLUMNS = [
        ['accounting_entry_lines', 'counterparty_type'],
        ['accounting_open_items', 'counterparty_type'],
    ];

    public function up(): void {
        $aliasByClass = array_flip(MorphMap::aliases());

        foreach (self::COLUMNS as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            $values = DB::table($table)->whereNotNull($column)->distinct()->pluck($column);
            foreach ($values as $value) {
                $value = (string) $value;
                $class = MorphMap::classFor($value);
                $alias = $class === null ? null : ($aliasByClass[$class] ?? null);
                if ($alias === null || $alias === $value) {
                    continue;
                }
                DB::table($table)->where($column, $value)->update([$column => $alias]);
            }
        }
    }

    public function down(): void {
        // Der Alias ist der Sollzustand jeder Laravel-Version; zurück zum Klassennamen gibt es nicht.
    }
};
