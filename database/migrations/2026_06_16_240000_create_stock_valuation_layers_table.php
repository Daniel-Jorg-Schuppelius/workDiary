<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_240000_create_stock_valuation_layers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIFO-Zugangsschichten der Bestandsbewertung (Feature 048, E3). Jeder
 * Wareneingang legt eine Schicht mit Restmenge und Einzelkosten an; Abgänge
 * verbrauchen die ältesten Schichten zuerst. Die Schicht-Kosten werden als
 * unveränderlicher Snapshot an die Abgangsbewegung geschrieben – spätere
 * Preisänderungen verändern historische Bewegungen nicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('stock_valuation_layers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->constrained('article_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('qty_remaining', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->string('currency', 3)->default('EUR');
            $table->foreignId('source_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamp('acquired_at');
            $table->timestamps();

            $table->index(['article_variant_id', 'warehouse_id', 'acquired_at'], 'stock_val_layers_fifo_idx');
            $table->index('organization_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_valuation_layers');
    }
};
