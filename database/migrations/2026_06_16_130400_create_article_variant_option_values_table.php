<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_130400_create_article_variant_option_values_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: die konkreten Optionswerte einer Variante (Feature 048, MVP-060).
 * Definiert zusammen die eindeutige Optionskombination der Variante.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('article_variant_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_variant_id')->constrained('article_variants')->cascadeOnDelete();
            $table->foreignId('article_option_value_id')->constrained('article_option_values')->cascadeOnDelete();

            $table->unique(['article_variant_id', 'article_option_value_id'], 'article_variant_optval_unique');
            $table->index('article_option_value_id', 'article_variant_optval_value_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('article_variant_option_values');
    }
};
