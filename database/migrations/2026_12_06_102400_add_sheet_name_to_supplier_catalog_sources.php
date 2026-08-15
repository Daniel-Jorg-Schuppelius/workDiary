<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102400_add_sheet_name_to_supplier_catalog_sources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * XLSX-Katalogquellen (Feature 050, MVP-541): Auswahl des Tabellenblatts einer
 * XLSX-Preisliste. NULL = erstes Blatt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->string('sheet_name', 64)->nullable()->after('has_header');
        });
    }

    public function down(): void {
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->dropColumn('sheet_name');
        });
    }
};
