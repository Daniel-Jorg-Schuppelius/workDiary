<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_04_100000_add_lock_version_to_idea_maps.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ideenlandkarten-Ausbau (Feature 054, MVP-136): karten-weite optimistische
 * Sperre für den Whole-Map-Sync des Canvas (SimpleMindMap). Der Canvas speichert
 * die ganze Karte gegen diese Version; jede Knoten-Mutation (auch aus der
 * Gliederung) inkrementiert sie, damit ein veralteter Canvas-Stand einen
 * sichtbaren Konflikt (HTTP 409) auslöst statt still zu überschreiben. Ergänzt
 * die feingranulare `idea_nodes.lock_version` (MVP-108), ersetzt sie nicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('idea_maps', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(1)->after('visibility');
        });
    }

    public function down(): void {
        Schema::table('idea_maps', function (Blueprint $table): void {
            $table->dropColumn('lock_version');
        });
    }
};
