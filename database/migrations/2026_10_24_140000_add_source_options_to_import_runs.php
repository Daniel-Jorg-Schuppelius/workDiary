<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_24_140000_add_source_options_to_import_runs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-438: format-spezifische Quellen-Optionen des Import-Laufs (z. B. die
 * optionale iCal-Kategorie-Allowlist), damit der asynchrone Job dieselben
 * Optionen wie die Preflight nutzt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('import_runs', function (Blueprint $table): void {
            $table->json('source_options')->nullable()->after('unresolved_values');
        });
    }

    public function down(): void {
        Schema::table('import_runs', function (Blueprint $table): void {
            $table->dropColumn('source_options');
        });
    }
};
