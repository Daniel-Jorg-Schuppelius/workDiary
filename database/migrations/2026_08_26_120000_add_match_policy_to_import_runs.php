<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_26_120000_add_match_policy_to_import_runs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import-Modus pro Lauf (MVP-103): `auto_create` (Default, bisheriges Verhalten)
 * oder `inbox_first` (unzuordenbare Zeilen → universelle Zuordnungs-Inbox statt
 * Blind-Anlage). Nur für Entitäten mit MatchProfile relevant.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('import_runs', function (Blueprint $table): void {
            $table->string('match_policy', 16)->default('auto_create')->after('encoding'); // auto_create | inbox_first
        });
    }

    public function down(): void {
        Schema::table('import_runs', function (Blueprint $table): void {
            $table->dropColumn('match_policy');
        });
    }
};
