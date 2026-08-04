<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_04_120100_create_etsy_receipts_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spiegel der Etsy-Bestellungen (Feature 101, MVP-495; Spaltenmuster
 * billbee_orders): Unique je (Org, receipt_id) — Sweep und Webhook upserten
 * idempotent dieselbe Zeile. Geldbeträge als Decimal (Money-Objekte
 * {amount, divisor} werden beim Ingest aufgelöst, nie float). Käufer/Items
 * datensparsam als json. `shipped_pushed_at` = Duplikatschutz des
 * Versand-Rückkanals (MVP-497). Kurze, explizite FK-/Index-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('etsy_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'etsyr_org_fk')->cascadeOnDelete();
            $table->unsignedBigInteger('receipt_id');
            $table->string('status', 32)->nullable();
            $table->boolean('was_paid')->default(false);
            $table->boolean('was_shipped')->default(false);
            $table->string('currency', 3)->nullable();
            $table->decimal('total_gross', 12, 2)->default(0);
            $table->decimal('total_shipping', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('buyer_external_id', 64)->nullable()->index('etsyr_buyer_idx');
            $table->json('buyer')->nullable();
            $table->json('items')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('etsy_modified_at')->nullable()->index('etsyr_modified_idx');
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'etsyr_cust_fk')->nullOnDelete();
            $table->string('inbox_status', 24)->default('open');
            $table->timestamp('shipped_pushed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'receipt_id'], 'etsyr_org_receipt_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('etsy_receipts');
    }
};
