<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_110000_create_article_supplies_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bezugsquellen-Stammdaten je Artikel/Lieferant (Feature 048, E4): Lieferanten-
 * artikelnummer, Mindestbestellmenge (MOQ), Lieferzeit, Verpackungseinheit und
 * Einkaufspreis. Genau eine bevorzugte Quelle je Artikel steuert die Vorschläge.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('article_supplies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('supplier_sku', 128)->nullable();
            $table->decimal('moq', 18, 4)->default(1);
            $table->decimal('pack_size', 18, 4)->default(1);
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->decimal('purchase_price', 18, 4)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'article_id', 'supplier_id'], 'article_supplies_unique');
            $table->index(['article_id', 'is_preferred'], 'article_supplies_preferred_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('article_supplies');
    }
};
