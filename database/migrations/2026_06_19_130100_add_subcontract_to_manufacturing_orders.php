<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_130100_add_subcontract_to_manufacturing_orders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpft einen Fertigungsauftrag mit dem Lieferantenauftrag der Fremdfertigung
 * (Feature 047/048, E7).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('manufacturing_orders', function (Blueprint $table): void {
            $table->foreignId('subcontract_purchase_order_id')->nullable()->after('procurement_mode')->constrained('purchase_orders')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('manufacturing_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subcontract_purchase_order_id');
        });
    }
};
