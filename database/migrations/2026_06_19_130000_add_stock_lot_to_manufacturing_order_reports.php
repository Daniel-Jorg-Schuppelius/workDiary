<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_130000_add_stock_lot_to_manufacturing_order_reports.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpft Gutmeldungen mit der erzeugten Charge (Feature 047/048, E7):
 * Grundlage für Los-Split/-Merge und chargenscharfe Rückverfolgung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('manufacturing_order_reports', function (Blueprint $table): void {
            $table->foreignId('stock_lot_id')->nullable()->after('manufacturing_order_id')->constrained('stock_lots')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('manufacturing_order_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_lot_id');
        });
    }
};
