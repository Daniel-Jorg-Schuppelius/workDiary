<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_130300_create_article_variants_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artikelvariante (Feature 048, MVP-060): die bestands- und fertigungsführende
 * Einheit. Eine Optionskombination ist je Hauptartikel eindeutig
 * (option_signature = sortierte Optionswert-Codes). organization_id ist bewusst
 * denormalisiert (aus dem Artikel), damit Bestand/Aufträge direkt org-scopen und
 * die SKU je Organisation eindeutig sein kann.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('article_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('gtin', 14)->nullable();
            $table->string('name')->nullable(); // ausgeschriebene Variantenbezeichnung (Snapshot-Basis)
            $table->string('status', 12)->default('active'); // ArticleStatus
            $table->boolean('is_default')->default(false);
            $table->string('option_signature', 191)->default(''); // sortierte Optionswert-Codes
            $table->decimal('purchase_price', 13, 4)->nullable();
            $table->decimal('sale_price', 13, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('article_id');
            $table->index('status');
            $table->unique(['article_id', 'option_signature'], 'article_variant_sig_unique');
            $table->unique(['organization_id', 'sku'], 'article_variant_sku_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('article_variants');
    }
};
