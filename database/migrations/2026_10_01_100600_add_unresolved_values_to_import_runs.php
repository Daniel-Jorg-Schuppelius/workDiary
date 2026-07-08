<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_100600_add_unresolved_values_to_import_runs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rang 58: unbekannte Tag-/Kategorie-Quellwerte aus der Preflight — der
 * Import wird erst bestätigbar, wenn sie über das Mapping-Formular
 * zugeordnet (oder ignoriert) wurden.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('import_runs', function (Blueprint $table): void {
            $table->json('unresolved_values')->nullable()->after('preview');
        });
    }

    public function down(): void {
        Schema::table('import_runs', function (Blueprint $table): void {
            $table->dropColumn('unresolved_values');
        });
    }
};
