<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_18_100100_create_shipments_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versandauftrag (Feature 059, MVP-128): hängt an einer Auslieferung
 * ({@see \App\Models\StockDelivery}) und trägt Carrier, Trackingnummer,
 * Carrier-Sendungs-ID (für Storno), Status und den Sendungsverlauf. Das
 * Label-PDF liegt als polymorpher `Attachment` am Versandauftrag. Die
 * versendeten Seriennummern hängen transitiv über `stock_serials.stock_delivery_id`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'shipment_org_fk')->cascadeOnDelete();
            $table->foreignId('stock_delivery_id')->nullable()->constrained('stock_deliveries', indexName: 'shipment_delivery_fk')->nullOnDelete();
            $table->string('carrier', 24);
            $table->string('status', 16)->default('draft'); // draft|labeled|in_transit|delivered|problem|cancelled
            $table->string('tracking_number')->nullable();
            $table->string('carrier_shipment_id')->nullable();
            $table->string('billing_number')->nullable();
            $table->json('recipient_snapshot')->nullable();  // verwendete Empfängeradresse (Nachweis)
            $table->json('events')->nullable();              // normalisierter Sendungsverlauf
            $table->timestamp('last_tracked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'shipment_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'shipment_org_status_idx');
            $table->index('tracking_number', 'shipment_tracking_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('shipments');
    }
};
