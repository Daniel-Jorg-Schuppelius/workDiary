<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_110100_create_purchase_orders_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bestellungen (Feature 048, E4). Kopf mit Lieferant, Ziel-Lager und Status;
 * der Wareneingang bucht gegen die Bestellzeilen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('number', 32);
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('status', 20)->default('draft'); // PurchaseOrderStatus
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'purchase_orders_org_number_uq');
            $table->index(['organization_id', 'status'], 'purchase_orders_org_status_idx');
            $table->index('supplier_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('purchase_orders');
    }
};
