<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_170400_create_woocommerce_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * WooCommerce-Import (Feature 175, MVP-1325): geprüfte Verbindung je
 * Organisation und Spiegel der Bestellungen. Zugangsdaten stehen in den
 * Plugin-Einstellungen, nicht hier.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('woocommerce_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('wooc_org_unique')->constrained('organizations', indexName: 'wooc_org_fk')->cascadeOnDelete();
            $table->string('source_id', 64)->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url')->nullable();
            $table->string('account_name')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('import_confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users', indexName: 'wooc_conf_by_fk')->nullOnDelete();
            $table->timestamp('checkpoint_at')->nullable();
            $table->timestamp('last_full_sync_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_counters')->nullable();
            $table->boolean('is_last_run_complete')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'wooc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'wooc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('woocommerce_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'wooo_org_fk')->cascadeOnDelete();
            $table->string('remote_order_id', 64);
            $table->string('order_number', 64)->nullable();
            $table->string('status', 16);
            $table->string('remote_status_code', 64)->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->string('buyer_external_id', 255)->nullable();
            $table->json('buyer')->nullable();
            $table->json('items')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('remote_modified_at')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'wooo_customer_fk')->nullOnDelete();
            $table->string('inbox_status', 16)->default('open');
            $table->timestamps();

            $table->unique(['organization_id', 'remote_order_id'], 'wooo_org_remote_unique');
            $table->index(['organization_id', 'ordered_at'], 'wooo_org_ordered_idx');
            $table->index(['organization_id', 'buyer_external_id'], 'wooo_org_buyer_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('woocommerce_orders');
        Schema::dropIfExists('woocommerce_connections');
    }
};
