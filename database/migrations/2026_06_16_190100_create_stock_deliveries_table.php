<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_190100_create_stock_deliveries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auslieferung eines Fertigerzeugnisses (Feature 047, MVP-074): bucht den
 * Bestand der konkreten Variante ab und übergibt die Position an das führende
 * Fakturasystem. Lager- und Faktura-Status sind GETRENNT, damit ein
 * Faktura-Fehler die erfolgte Lagerbuchung nicht verbirgt. Preis-, SKU- und
 * Bezeichnungssnapshot werden zum Auslieferungszeitpunkt eingefroren.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('stock_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('manufacturing_order_id')->nullable()->constrained('manufacturing_orders')->nullOnDelete();
            $table->foreignId('article_variant_id')->constrained('article_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->decimal('quantity', 18, 4);
            $table->string('unit', 20);
            $table->string('sku_snapshot', 64)->nullable();
            $table->string('name_snapshot');
            $table->decimal('unit_price_snapshot', 13, 4)->nullable();
            $table->string('currency', 3)->default('EUR');

            $table->string('stock_status', 12)->default('delivered'); // delivered/cancelled
            $table->string('facturation_status', 16)->default('pending'); // DeliveryFacturationStatus
            $table->string('facturation_target', 16)->nullable(); // workdiary/lexoffice/datev
            $table->string('external_id', 128)->nullable();

            $table->timestamp('delivered_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('manufacturing_order_id');
            $table->index(['article_variant_id', 'warehouse_id'], 'stock_deliveries_variant_wh_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_deliveries');
    }
};
