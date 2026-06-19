<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_220000_create_stock_serials_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seriennummern-Einzelstückregister (Feature 047/048, E2). Jede Seriennummer
 * existiert genau einmal je Organisation + Artikel (harte Dublettensperre); der
 * Lebenslauf (Status) ist lückenlos und über die hinterlegte Auslieferung an den
 * Kunden gebunden – Grundlage für Versandnachweis und Garantie-/Betrugsprüfung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('stock_serials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->constrained('article_variants')->cascadeOnDelete();

            $table->string('serial_no', 80);
            $table->string('status', 12)->default('in_stock');   // SerialStatus
            $table->string('source', 16)->default('manufactured'); // SerialSource

            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('manufacturing_order_id')->nullable()->constrained('manufacturing_orders')->nullOnDelete();
            $table->foreignId('stock_delivery_id')->nullable()->constrained('stock_deliveries')->nullOnDelete();

            $table->string('blocked_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('shipped_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'article_id', 'serial_no'], 'stock_serials_org_article_no_uq');
            $table->index(['organization_id', 'status'], 'stock_serials_org_status_idx');
            $table->index(['article_variant_id', 'status'], 'stock_serials_variant_status_idx');
            $table->index('serial_no', 'stock_serials_no_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_serials');
    }
};
