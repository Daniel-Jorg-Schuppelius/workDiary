<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_250100_add_lot_to_stock_valuation_layers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpft Bewertungsschichten mit Chargen und hinterlegt das Verfallsdatum
 * direkt an der Schicht (Feature 047/048, E2/E3): Grundlage für FEFO-Entnahme
 * (ältestes Verfallsdatum zuerst) und chargenbezogene Bewertung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('stock_valuation_layers', function (Blueprint $table): void {
            $table->foreignId('stock_lot_id')->nullable()->after('warehouse_id')->constrained('stock_lots')->nullOnDelete();
            $table->date('best_before')->nullable()->after('acquired_at');
            $table->index(['article_variant_id', 'warehouse_id', 'best_before'], 'stock_val_layers_fefo_idx');
        });
    }

    public function down(): void {
        Schema::table('stock_valuation_layers', function (Blueprint $table): void {
            $table->dropIndex('stock_val_layers_fefo_idx');
            $table->dropConstrainedForeignId('stock_lot_id');
            $table->dropColumn('best_before');
        });
    }
};
