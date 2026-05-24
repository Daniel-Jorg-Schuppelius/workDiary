<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_11_130000_add_relative_recurrence_to_holidays_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('holidays')) {
            return;
        }

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if (! $isSqlite && Schema::hasColumn('holidays', 'date')) {
            Schema::table('holidays', function (Blueprint $table): void {
                // date wird für relative Feiertage NULL (NULL != NULL → unique constraint bleibt)
                $table->date('date')->nullable()->change();
            });
        }

        Schema::table('holidays', function (Blueprint $table) use ($isSqlite): void {
            if (! Schema::hasColumn('holidays', 'recurrence_type')) {
                $column = $table->string('recurrence_type', 10)->default('fixed');
                if (! $isSqlite) {
                    $column->after('is_recurring');
                }
            }
            // 0=So, 1=Mo, 2=Di, 3=Mi, 4=Do, 5=Fr, 6=Sa (Carbon-Konstanten)
            if (! Schema::hasColumn('holidays', 'recurrence_weekday')) {
                $column = $table->tinyInteger('recurrence_weekday')->nullable();
                if (! $isSqlite) {
                    $column->after('recurrence_type');
                }
            }
            // 1–4 = Nth, -1 = letzter
            if (! Schema::hasColumn('holidays', 'recurrence_week')) {
                $column = $table->tinyInteger('recurrence_week')->nullable();
                if (! $isSqlite) {
                    $column->after('recurrence_weekday');
                }
            }
            // 1–12 oder NULL = jeden Monat
            if (! Schema::hasColumn('holidays', 'recurrence_month')) {
                $column = $table->tinyInteger('recurrence_month')->nullable();
                if (! $isSqlite) {
                    $column->after('recurrence_week');
                }
            }
        });
    }

    public function down(): void {
        if (! Schema::hasTable('holidays')) {
            return;
        }

        Schema::table('holidays', function (Blueprint $table): void {
            $dropColumns = array_values(array_filter([
                Schema::hasColumn('holidays', 'recurrence_type') ? 'recurrence_type' : null,
                Schema::hasColumn('holidays', 'recurrence_weekday') ? 'recurrence_weekday' : null,
                Schema::hasColumn('holidays', 'recurrence_week') ? 'recurrence_week' : null,
                Schema::hasColumn('holidays', 'recurrence_month') ? 'recurrence_month' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });

        if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasColumn('holidays', 'date')) {
            Schema::table('holidays', function (Blueprint $table): void {
                $table->date('date')->nullable(false)->change();
            });
        }
    }
};
