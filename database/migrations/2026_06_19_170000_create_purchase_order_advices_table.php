<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_170000_create_purchase_order_advices_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lieferavis (Advance Shipping Notice) zu einer Bestellung (Feature 048, E4):
 * der Lieferant kündigt eine Sendung an; daraus lässt sich der Wareneingang
 * gegen die Bestellzeilen buchen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_order_advices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('reference', 128)->nullable(); // Avis-/Lieferschein-Nr. des Lieferanten
            $table->string('carrier', 128)->nullable();
            $table->string('tracking', 128)->nullable();
            $table->date('expected_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->string('status', 12)->default('announced'); // AdviceStatus
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'po_advices_org_status_idx');
            $table->index('purchase_order_id');
        });

        Schema::create('purchase_order_advice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('purchase_order_advice_id')->constrained('purchase_order_advices')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
            $table->decimal('qty', 18, 4);
            $table->timestamps();

            $table->index('purchase_order_advice_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('purchase_order_advice_lines');
        Schema::dropIfExists('purchase_order_advices');
    }
};
