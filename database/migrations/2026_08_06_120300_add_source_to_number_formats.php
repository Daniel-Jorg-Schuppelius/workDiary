<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_06_120300_add_source_to_number_formats.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markiert je Nummernkreis (Scope) die Hoheit:
 *  - 'local'    : workDiary vergibt die Nummer über NumberSequenceService
 *  - 'external' : Lexoffice ist führend; lokal wird nur eine Entwurfsnummer
 *                 vergeben, die beim Push/Sync durch die Lexoffice-Nummer
 *                 überschrieben wird.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('number_formats', function (Blueprint $table): void {
            $table->string('source', 16)->default('local')->after('scope');
        });
    }

    public function down(): void {
        Schema::table('number_formats', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
