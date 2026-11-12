<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_320200_add_restricted_columns_to_export_runs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spalten mit eigenem Recht, die eine Exportdatei enthält (MVP-1625): Der
 * Download verlangt dasselbe Recht wie der Export. Bestandsläufe bleiben ohne
 * Eintrag — ihre Dateien entstanden vor der Regel.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('export_runs') && ! Schema::hasColumn('export_runs', 'restricted_columns')) {
            Schema::table('export_runs', function (Blueprint $table): void {
                $table->json('restricted_columns')->nullable()->after('filters');
            });
        }
    }

    public function down(): void {
        if (Schema::hasColumn('export_runs', 'restricted_columns')) {
            Schema::table('export_runs', function (Blueprint $table): void {
                $table->dropColumn('restricted_columns');
            });
        }
    }
};
