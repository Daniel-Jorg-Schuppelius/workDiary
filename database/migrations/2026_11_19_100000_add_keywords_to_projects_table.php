<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_19_100000_add_keywords_to_projects_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Synonyme für die Schlüsselwort-Zuordnung importierter Zeiten (MVP-483).
 * Der Projektname selbst wird zur Laufzeit abgeleitet — hier stehen nur
 * zusätzliche Begriffe, die im Namen nicht vorkommen ("Lohn" → "LODAS").
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('projects', function (Blueprint $table): void {
            $table->json('keywords')->nullable()->after('description');
        });
    }

    public function down(): void {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('keywords');
        });
    }
};
