<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102900_create_article_sale_price_histories.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VK-Preisverlauf am Artikelstamm (Feature 107, W10 — Nutzer-Entscheidung
 * 2026-08-16): historisiert `default_sale_price` bzw. Varianten-`sale_price`
 * bei Anlage und Änderung. Primärer Treiber: DATPREIS-Export „Änderungen seit
 * Datum" statt Vollstand; daneben Nachvollziehbarkeit der VK-Entwicklung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('article_sale_price_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles', indexName: 'asph_article_fk')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->nullable()->constrained('article_variants', indexName: 'asph_variant_fk')->cascadeOnDelete();
            $table->decimal('sale_price', 18, 4);
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('recorded_at');

            $table->index(['article_id', 'recorded_at'], 'asph_article_recorded_idx');
            $table->index(['organization_id', 'recorded_at'], 'asph_org_recorded_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('article_sale_price_histories');
    }
};
