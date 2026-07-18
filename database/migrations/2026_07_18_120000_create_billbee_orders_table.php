<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_18_120000_create_billbee_orders_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billbee-Bestellspiegel (Feature 093, MVP-433): plugin-eigene Projektion der
 * Multichannel-Bestellungen (Muster carddav_cards). Kein Blind-Import —
 * Kundenzuordnung läuft Inbox-First; billbee_modified_at trägt den
 * Polling-Aufholpunkt (modifiedAtMin). Kurze, explizite Index-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('billbee_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'bbo_org_fk')->cascadeOnDelete();
            $table->string('billbee_order_id', 64);
            $table->string('external_order_id', 128)->nullable();
            $table->string('order_number', 128)->nullable();
            $table->string('channel', 64)->nullable();
            $table->unsignedSmallInteger('state')->default(0);
            $table->string('currency', 3)->nullable();
            $table->decimal('total_gross', 12, 2)->default(0);
            $table->string('buyer_external_id', 64)->nullable()->index('bbo_buyer_idx');
            $table->json('buyer')->nullable();
            $table->json('items')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('billbee_modified_at')->nullable()->index('bbo_modified_idx');
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'bbo_cust_fk')->nullOnDelete();
            $table->string('inbox_status', 24)->default('open');
            $table->timestamps();

            $table->unique(['organization_id', 'billbee_order_id'], 'bbo_org_order_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('billbee_orders');
    }
};
