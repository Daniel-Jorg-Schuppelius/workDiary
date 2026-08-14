<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102300_add_user_target_to_import_value_mappings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Benutzer-Ziele im Import-Wertmapping: Zeitimporte (Projektzeiten,
 * Stempelungen, Schichten) referenzieren Benutzer per E-Mail — weicht die
 * Quell-Adresse vom Konto ab, ordnet das Mapping-Formular sie einem Benutzer
 * der Organisation zu (`target_kind` = 'user') statt hart zu scheitern
 * (Muster Klassifikations-Ziele, A13).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('import_value_mappings', function (Blueprint $table): void {
            // Ziel-Benutzer (nullable — nur bei target_kind 'user').
            $table->foreignId('user_id')->nullable()->after('classification_id')
                ->constrained('users')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::table('import_value_mappings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
