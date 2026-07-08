<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_102500_create_service_catalog_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 065, P4 (MVP-154): Katalog-Dreiteilung business_services →
 * service_offerings → request_items (bestellbare Einheit mit Formular aus
 * dem 032-Vorlagensystem und Genehmigungskette) + service_requests
 * (1:1 zum Ticket, eingefrorene Snapshots) (Genehmigungsschritte liegen generisch in `approvals` — 102800).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('business_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'bsv_org_fk')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name'], 'bsv_org_name_unique');
        });

        Schema::create('service_offerings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'sof_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('business_service_id')
                ->constrained('business_services', indexName: 'sof_service_fk')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'rqi_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('service_offering_id')
                ->constrained('service_offerings', indexName: 'rqi_offering_fk')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->foreignId('form_template_id')->nullable()
                ->constrained('form_templates', indexName: 'rqi_form_fk')
                ->nullOnDelete();
            $table->json('approval_chain')->nullable(); // Schritte: [{step, approver: {type: role|user, value}}]
            $table->foreignId('sla_contract_id')->nullable()
                ->constrained('sla_contracts', indexName: 'rqi_sla_fk')
                ->nullOnDelete();
            $table->string('fulfillment', 20)->default('task'); // task|project|diary|procedure|external
            $table->json('fulfillment_config')->nullable();     // z. B. procedure_template_id
            $table->unsignedInteger('version')->default(1);
            $table->json('visibility')->nullable(); // org/customer/contract/role-Filter
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'srq_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('service_ticket_id')
                ->constrained('service_tickets', indexName: 'srq_ticket_fk')
                ->cascadeOnDelete();
            $table->foreignId('request_item_id')
                ->constrained('request_items', indexName: 'srq_item_fk')
                ->cascadeOnDelete();
            $table->json('form_snapshot')->nullable();    // Formular + Antworten, eingefroren
            $table->json('catalog_snapshot');             // Katalogstand, eingefroren
            $table->string('status', 20)->default('pending_approval'); // draft|pending_approval|approved|rejected|fulfilling|done
            $table->nullableMorphs('fulfilled', 'srq_fulfilled_idx');  // Task/Project/DiaryEntry/ProcedureRun
            $table->timestamps();

            $table->unique('service_ticket_id', 'srq_ticket_unique'); // 1:1 zum Ticket
        });

    }

    public function down(): void {
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('request_items');
        Schema::dropIfExists('service_offerings');
        Schema::dropIfExists('business_services');
    }
};
