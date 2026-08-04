<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_30_101000_create_disposal_module_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 100 (Phase 56, MVP-474/475): Entsorgungsakte für Altgeräte-Rücknahme.
 *
 * Entscheidungen:
 * - Kindtabellen (items/treatments/handovers/events) ohne eigene
 *   organization_id — Mandantengrenze transitiv über disposal_jobs
 *   (Muster Protokoll/TimeExport, Allow-List im TenantTraitCoverageTest).
 * - `is_hazardous` wird aus dem AVV-Schlüssel abgeleitet
 *   (CommonToolkit\ValueObjects\WasteCode), nie frei gesetzt.
 * - Kundennachweis-PDF wird als DMS-Dokument (record_document_id) geführt —
 *   Versionierung über document_versions, Portal-Ausgabe über die
 *   bestehende Kundenfreigabe.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('disposal_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('status', 20)->default('draft');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('diary_entry_id')->nullable()->constrained('diary_entries')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('picked_up_on')->nullable();
            $table->decimal('total_weight_kg', 10, 3)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('record_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('signer_name', 120)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signature_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->char('signature_hash', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'disposal_jobs_org_number_uq');
            $table->index(['organization_id', 'status'], 'disposal_jobs_org_status_idx');
            $table->index(['customer_id', 'picked_up_on'], 'disposal_jobs_customer_idx');
        });

        Schema::create('disposal_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('disposal_job_id')->constrained('disposal_jobs')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('category', 120);
            $table->string('manufacturer', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->string('condition_note', 180)->nullable();
            $table->string('avv_code', 12);
            $table->boolean('is_hazardous')->default(false);
            $table->boolean('has_data_storage')->default(false);
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['disposal_job_id', 'sort_order'], 'disposal_items_order_idx');
        });

        Schema::create('data_media_treatments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('disposal_item_id')->constrained('disposal_items')->cascadeOnDelete();
            $table->string('media_type', 40);
            $table->string('method', 40);
            $table->char('din_category', 1);
            $table->unsignedTinyInteger('security_level');
            $table->unsignedTinyInteger('protection_class')->nullable();
            $table->timestamp('treated_at');
            $table->foreignId('performed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('evidence_reference', 180)->nullable();
            $table->timestamps();
        });

        Schema::create('disposal_handovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('disposal_job_id')->constrained('disposal_jobs')->cascadeOnDelete();
            $table->foreignId('external_contact_id')->constrained('external_contacts')->cascadeOnDelete();
            $table->string('proof_type', 30);
            $table->string('document_number', 80);
            $table->date('handed_over_on');
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('certificate_reference', 180)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['disposal_job_id', 'handed_over_on'], 'disposal_handovers_job_idx');
        });

        Schema::create('disposal_job_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('disposal_job_id')->constrained('disposal_jobs')->cascadeOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at');

            $table->index(['disposal_job_id', 'created_at'], 'disposal_job_events_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('disposal_job_events');
        Schema::dropIfExists('disposal_handovers');
        Schema::dropIfExists('data_media_treatments');
        Schema::dropIfExists('disposal_items');
        Schema::dropIfExists('disposal_jobs');
    }
};
