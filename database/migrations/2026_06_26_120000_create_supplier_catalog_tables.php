<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_26_120000_create_supplier_catalog_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lieferantenkataloge (Feature 050, MVP-091/092/094): Katalogquellen, externe
 * Katalogartikel und historisierte Einkaufspreise. Der interne Artikelstamm
 * bleibt kanonisch — Katalogartikel sind zunächst nur Importkandidaten/
 * Bezugsquellen (Verknüpfung in MVP-093). Kurze, explizite Index-/FK-Namen
 * wegen des MySQL-64-Zeichen-Limits.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_catalog_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'scs_org_fk')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers', indexName: 'scs_sup_fk')->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('format', 16)->default('csv');         // CatalogSourceFormat
            $table->string('source_type', 16)->default('upload'); // upload/http/ftp/sftp (nur upload implementiert)
            $table->string('encoding', 32)->default('UTF-8');
            $table->string('delimiter', 4)->default(';');
            $table->boolean('has_header')->default(true);
            $table->string('decimal_separator', 1)->default(',');
            $table->boolean('active')->default(true);
            $table->timestamp('last_imported_at')->nullable();
            $table->string('last_file_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'supplier_id'], 'scs_org_sup_idx');
        });

        Schema::create('supplier_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'sci_org_fk')->cascadeOnDelete();
            $table->foreignId('supplier_catalog_source_id')->constrained('supplier_catalog_sources', indexName: 'sci_src_fk')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers', indexName: 'sci_sup_fk')->cascadeOnDelete();
            $table->string('external_no', 100);          // Lieferanten-Artikelnummer (Schlüssel je Quelle)
            $table->string('manufacturer_no', 100)->nullable();
            $table->string('manufacturer', 191)->nullable();
            $table->string('brand', 191)->nullable();
            $table->string('gtin', 20)->nullable();
            $table->string('category', 191)->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('product_url', 1024)->nullable();
            $table->decimal('purchase_price', 18, 4)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('pack_size', 18, 4)->default(1);
            $table->decimal('base_qty', 18, 4)->default(1);
            $table->string('availability', 64)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('status', 16)->default('new'); // CatalogItemStatus
            $table->string('raw_hash', 64);                // Rohdaten-Hash zur Änderungserkennung
            // Verknüpfung mit dem internen Stamm (MVP-093) — vorerst nullable.
            $table->foreignId('article_id')->nullable()->constrained('articles', indexName: 'sci_art_fk')->nullOnDelete();
            $table->foreignId('article_variant_id')->nullable()->constrained('article_variants', indexName: 'sci_var_fk')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_catalog_source_id', 'external_no'], 'sci_src_extno_unique');
            $table->index(['organization_id', 'status'], 'sci_org_status_idx');
            $table->index('gtin', 'sci_gtin_idx');
        });

        Schema::create('supplier_catalog_item_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_catalog_item_id')->constrained('supplier_catalog_items', indexName: 'scip_item_fk')->cascadeOnDelete();
            $table->decimal('purchase_price', 18, 4);
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['supplier_catalog_item_id', 'captured_at'], 'scip_item_captured_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('supplier_catalog_item_prices');
        Schema::dropIfExists('supplier_catalog_items');
        Schema::dropIfExists('supplier_catalog_sources');
    }
};
