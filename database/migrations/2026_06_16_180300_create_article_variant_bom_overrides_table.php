<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_180300_create_article_variant_bom_overrides_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Varianten-spezifische Überschreibungen der Basis-Stückliste (Feature 047,
 * MVP-061). Bezieht sich über den stabilen `position_code` auf eine
 * Basisposition (außer bei `add`). Die aufgelöste Stückliste eines
 * Fertigungsauftrags entsteht aus Basis + Overrides der konkreten Variante.
 * Mandantengrenze transitiv über die Variante.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('article_variant_bom_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_variant_id')->constrained('article_variants')->cascadeOnDelete();
            $table->string('position_code', 40);
            $table->string('action', 16); // BomOverrideAction

            // Felder für override_qty / add:
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('quantity_kind', 12)->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('ratio_part', 18, 4)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('waste_surcharge', 6, 3)->nullable();
            $table->boolean('is_tool')->default(false);
            $table->timestamps();

            $table->unique(['article_variant_id', 'position_code', 'action'], 'article_variant_bom_override_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('article_variant_bom_overrides');
    }
};
