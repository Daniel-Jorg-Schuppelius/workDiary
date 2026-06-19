<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_170000_create_stock_valuations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laufende Bewertung je Variante und Lagerort (Feature 048, MVP-070):
 * gleitender Durchschnittspreis (`avg_cost`) und bewertete Menge
 * (`qty_on_hand`). Der unveränderliche Kostensnapshot je Bewegung steht in
 * stock_movements (cost_unit/cost_total). Spätere Preisänderungen verändern
 * historische Bewegungen/Kalkulationen nicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('stock_valuations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('article_variant_id')->constrained('article_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('avg_cost', 18, 4)->default(0);
            $table->decimal('qty_on_hand', 18, 4)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();

            $table->index('organization_id');
            $table->unique(['article_variant_id', 'warehouse_id'], 'stock_valuation_variant_wh_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_valuations');
    }
};
