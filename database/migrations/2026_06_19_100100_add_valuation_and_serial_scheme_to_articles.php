<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_100100_add_valuation_and_serial_scheme_to_articles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artikelspezifische Konfiguration (Feature 048, E2/E3): `valuation_method`
 * überschreibt das Org-Bewertungsverfahren je Artikel; `serial_scheme` hinterlegt
 * ein eigenes Seriennummernschema (Präfix/Stellen) für die Eigenfertigung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('valuation_method', 16)->nullable()->after('serial_required');
            $table->json('serial_scheme')->nullable()->after('valuation_method');
        });
    }

    public function down(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['valuation_method', 'serial_scheme']);
        });
    }
};
