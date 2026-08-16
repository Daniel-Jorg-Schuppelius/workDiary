<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102600_add_datanorm_conditions_to_supplier_catalogs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATANORM-Konditionen (Feature 107, MVP-554): Katalogartikel lernen
 * Mengeneinheit, Rabattgruppe, Preisart (Liste/Netto/…) und die gelieferte
 * Preiseinheit (für DATPREIS-V4-Sätze ohne eigene Preiseinheit). Rabatt- und
 * Warengruppen einer Quelle werden als eigene Stammtabellen geführt (RAB/WRG
 * sind Volllieferungen je Quelle). Quellen erhalten eine erwartete
 * Kundennummer für den K-Kontrollsatz kundenindividueller Preisdateien.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_catalog_items', function (Blueprint $table): void {
            $table->string('unit', 16)->nullable()->after('base_qty');
            $table->string('discount_group', 8)->nullable()->after('unit');
            $table->string('price_type', 16)->nullable()->after('discount_group');
            $table->unsignedInteger('price_unit_amount')->default(1)->after('price_type');
        });

        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->string('expected_customer_no', 32)->nullable()->after('sheet_name');
        });

        Schema::create('supplier_catalog_discount_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_catalog_source_id')->constrained('supplier_catalog_sources', indexName: 'scdg_source_fk')->cascadeOnDelete();
            $table->string('code', 8);
            $table->string('kind', 16); // discount | factor | surcharge
            $table->decimal('value', 10, 4);
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['supplier_catalog_source_id', 'code'], 'scdg_source_code_unique');
        });

        Schema::create('supplier_catalog_product_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_catalog_source_id')->constrained('supplier_catalog_sources', indexName: 'scpg_source_fk')->cascadeOnDelete();
            $table->string('main_group', 8);
            $table->string('group', 16)->nullable();
            $table->string('label');
            $table->timestamps();

            $table->unique(['supplier_catalog_source_id', 'main_group', 'group'], 'scpg_source_group_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('supplier_catalog_product_groups');
        Schema::dropIfExists('supplier_catalog_discount_groups');
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->dropColumn('expected_customer_no');
        });
        Schema::table('supplier_catalog_items', function (Blueprint $table): void {
            $table->dropColumn(['unit', 'discount_group', 'price_type', 'price_unit_amount']);
        });
    }
};
