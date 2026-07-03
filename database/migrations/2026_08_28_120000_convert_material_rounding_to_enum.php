<?php
/*
 * Created on   : Thu Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_28_120000_convert_material_rounding_to_enum.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Stellt die Material-Rundung (Feature 047/048) vom app-lokalen String
 * ('none'/'up'/'down') auf die Toolkit-Enum CommonToolkit\Enums\RoundingMode
 * um: NULL = keine Rundung (SCALE-genau), sonst Rundung auf ganze Einheiten.
 * Bestandsabbildung: up→ceil, down→floor (für nicht-negative Mengen identisch
 * zum bisherigen Verhalten), none→NULL.
 */
return new class extends Migration {
    /** @var list<string> Tabellen mit der rounding-Spalte */
    private const TABLES = ['procedure_material_requirements', 'manufacturing_order_materials'];

    public function up(): void {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->string('rounding', 12)->nullable()->default(null)->change();
            });

            DB::table($table)->where('rounding', 'up')->update(['rounding' => 'ceil']);
            DB::table($table)->where('rounding', 'down')->update(['rounding' => 'floor']);
            DB::table($table)->whereIn('rounding', ['none', ''])->update(['rounding' => null]);
        }
    }

    public function down(): void {
        foreach (self::TABLES as $table) {
            DB::table($table)->where('rounding', 'ceil')->update(['rounding' => 'up']);
            DB::table($table)->where('rounding', 'floor')->update(['rounding' => 'down']);
            DB::table($table)->whereNull('rounding')->update(['rounding' => 'none']);

            Schema::table($table, function (Blueprint $t): void {
                $t->string('rounding', 12)->default('none')->change();
            });
        }
    }
};
