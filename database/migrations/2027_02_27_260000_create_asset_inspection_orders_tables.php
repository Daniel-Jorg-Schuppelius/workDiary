<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_260000_create_asset_inspection_orders_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-938: Prüfaufträge an Dienstleister mit Angebot und Ergebnisrückmeldung. */
return new class extends Migration {
    public function up(): void {
        Schema::create('asset_inspection_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'insp_orders_org_fk')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers', indexName: 'insp_orders_supplier_fk')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('status', 16);
            $table->string('token_hash', 128)->unique('insp_orders_token_uq');
            $table->string('recipient_email', 255);
            $table->timestamp('expires_at');
            $table->decimal('offer_amount', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->date('offer_planned_on')->nullable();
            $table->text('offer_note')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users', indexName: 'insp_orders_accepted_by_fk')->nullOnDelete();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'insp_orders_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_inspection_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'insp_order_items_org_fk')->cascadeOnDelete();
            $table->foreignId('asset_inspection_order_id')->constrained('asset_inspection_orders', indexName: 'insp_order_items_order_fk')->cascadeOnDelete();
            $table->foreignId('asset_inspection_schedule_id')->nullable()->constrained('asset_inspection_schedules', indexName: 'insp_order_items_schedule_fk')->nullOnDelete();
            $table->foreignId('asset_compliance_assignment_id')->constrained('asset_compliance_assignments', indexName: 'insp_order_items_assignment_fk')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets', indexName: 'insp_order_items_asset_fk')->cascadeOnDelete();
            $table->string('result', 32)->nullable();
            $table->date('performed_on')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('certificate_no', 120)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('asset_inspection_event_id')->nullable()->constrained('asset_inspection_events', indexName: 'insp_order_items_event_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['asset_inspection_order_id', 'asset_compliance_assignment_id'], 'insp_order_items_order_assignment_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('asset_inspection_order_items');
        Schema::dropIfExists('asset_inspection_orders');
    }
};
