<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_103400_add_copper_fields_and_price_tiers_to_articles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elektro-Metadaten am eigenen Artikel (Feature 107, MVP-605): Kupferdaten
 * (DEL-Basis im Preis + Gewichtsanteil) und Verkaufs-Staffelpreise — Quelle
 * der Z-Sätze im eigenen DATANORM-Export.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('articles', function (Blueprint $table): void {
            // Kupfergewicht in kg je Einheit; DEL-Basis in €/100 kg, die im
            // Verkaufspreis bereits enthalten ist (deutsche Methode).
            $table->decimal('copper_weight', 10, 4)->nullable()->after('assembly_minutes');
            $table->decimal('copper_base_price', 12, 4)->nullable()->after('copper_weight');
        });

        Schema::create('article_price_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_qty', 12, 2);
            $table->decimal('unit_price', 18, 4);
            $table->timestamps();
            $table->unique(['article_id', 'min_qty']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('article_price_tiers');
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['copper_weight', 'copper_base_price']);
        });
    }
};
