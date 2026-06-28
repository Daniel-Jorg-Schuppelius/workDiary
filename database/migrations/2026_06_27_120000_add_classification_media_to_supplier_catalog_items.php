<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_27_120000_add_classification_media_to_supplier_catalog_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klassifikationen (eCl@ss/UNSPSC) und Medien (Produktbild, Datenblatt) am
 * Katalogartikel (Feature 050, „Später"). Werden v. a. aus BMEcat befüllt, sind
 * aber auch im CSV-Mapping verfügbar.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_catalog_items', function (Blueprint $table): void {
            $table->string('classification_system', 32)->nullable()->after('category'); // eclass / unspsc / …
            $table->string('classification_code', 64)->nullable()->after('classification_system');
            $table->string('image_url', 1024)->nullable()->after('product_url');
            $table->string('datasheet_url', 1024)->nullable()->after('image_url');
        });
    }

    public function down(): void {
        Schema::table('supplier_catalog_items', function (Blueprint $table): void {
            $table->dropColumn(['classification_system', 'classification_code', 'image_url', 'datasheet_url']);
        });
    }
};
