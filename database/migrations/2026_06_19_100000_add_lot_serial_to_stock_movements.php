<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_100000_add_lot_serial_to_stock_movements.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpft Lagerbewegungen mit Charge und Seriennummer (Feature 047/048, E2).
 * Damit ist jede Bewegung chargen-/serienscharf rückverfolgbar; der Bestand je
 * Charge lässt sich direkt aus dem append-only Journal ableiten.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('stock_lot_id')->nullable()->after('warehouse_id')->constrained('stock_lots')->nullOnDelete();
            $table->foreignId('stock_serial_id')->nullable()->after('stock_lot_id')->constrained('stock_serials')->nullOnDelete();
            $table->index(['stock_lot_id'], 'stock_movements_lot_idx');
        });
    }

    public function down(): void {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_movements_lot_idx');
            $table->dropConstrainedForeignId('stock_lot_id');
            $table->dropConstrainedForeignId('stock_serial_id');
        });
    }
};
