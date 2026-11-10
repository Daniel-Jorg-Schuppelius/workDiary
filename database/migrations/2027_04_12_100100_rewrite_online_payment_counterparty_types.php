<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_12_100100_rewrite_online_payment_counterparty_types.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Support\MorphMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Nachtrag zu `2027_03_24_090000` (MVP-1523): Der Buchungsadapter für
 * Online-Zahlungen schrieb weiter den Klassennamen in `counterparty_type`;
 * Zeilen seit jener Umschreibung lesen sich sonst nicht (`ClassMorphViolationException`).
 * Idempotent, Aliase und Fremdwerte bleiben.
 */
return new class extends Migration {
    /** @var list<array{0: string, 1: string}> Tabelle, Typspalte */
    private const array COLUMNS = [
        ['accounting_entry_lines', 'counterparty_type'],
        ['accounting_open_items', 'counterparty_type'],
    ];

    public function up(): void {
        $aliasByClass = array_flip(MorphMap::aliases());

        foreach (self::COLUMNS as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            foreach (DB::table($table)->whereNotNull($column)->distinct()->pluck($column) as $value) {
                $value = (string) $value;
                $class = MorphMap::classFor($value);
                $alias = $class === null ? null : ($aliasByClass[$class] ?? null);
                if ($alias !== null && $alias !== $value) {
                    DB::table($table)->where($column, $value)->update([$column => $alias]);
                }
            }
        }
    }

    public function down(): void {
        // Der Alias ist der Sollzustand; zurück zum Klassennamen gibt es nicht.
    }
};
