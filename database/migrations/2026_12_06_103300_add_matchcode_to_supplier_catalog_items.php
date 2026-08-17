<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_103300_add_matchcode_to_supplier_catalog_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matchcode am Katalogartikel (Feature 107, MVP-601): Kurz-Suchbegriff des
 * Lieferanten aus dem DATANORM-A-/B-Satz — durchsuchbar in der Katalogansicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_catalog_items', function (Blueprint $table): void {
            $table->string('matchcode', 40)->nullable()->after('gtin')->index();
        });
    }

    public function down(): void {
        Schema::table('supplier_catalog_items', function (Blueprint $table): void {
            $table->dropIndex(['matchcode']);
            $table->dropColumn('matchcode');
        });
    }
};
