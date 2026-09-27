<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_150000_create_recalls_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-921/922: Rückrufaktionen mit betroffenen Auslieferungen je Kunde und Rücklauf über die Reklamation. */
return new class extends Migration {
    public function up(): void {
        Schema::create('recalls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'recalls_org_fk')->cascadeOnDelete();
            $table->string('number', 40)->nullable();
            $table->foreignId('article_variant_id')->constrained('article_variants', indexName: 'recalls_variant_fk')->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('status', 24);
            $table->string('title', 200);
            $table->text('reason');
            $table->text('customer_message')->nullable();
            $table->json('manufacturing_order_ids')->nullable();
            $table->date('delivered_from')->nullable();
            $table->date('delivered_until')->nullable();
            $table->json('serial_numbers')->nullable();
            $table->boolean('is_blocking_stock')->default(true);
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'recalls_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'recalls_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'recalls_org_status_idx');
            $table->unique(['organization_id', 'number'], 'recalls_org_number_uq');
        });

        Schema::create('recall_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'recall_items_org_fk')->cascadeOnDelete();
            $table->foreignId('recall_id')->constrained('recalls', indexName: 'recall_items_recall_fk')->cascadeOnDelete();
            $table->foreignId('stock_delivery_id')->nullable()->constrained('stock_deliveries', indexName: 'recall_items_delivery_fk')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'recall_items_customer_fk')->nullOnDelete();
            $table->foreignId('stock_serial_id')->nullable()->constrained('stock_serials', indexName: 'recall_items_serial_fk')->nullOnDelete();
            $table->foreignId('claim_case_id')->nullable()->constrained('claim_cases', indexName: 'recall_items_claim_fk')->nullOnDelete();
            $table->decimal('quantity', 14, 4)->nullable();
            $table->string('status', 24);
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['recall_id', 'status'], 'recall_items_recall_status_idx');
            $table->index(['customer_id', 'status'], 'recall_items_customer_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('recall_items');
        Schema::dropIfExists('recalls');
    }
};
