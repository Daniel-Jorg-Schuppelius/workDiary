<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_04_130000_create_asset_compliance_module_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 075 (Phase 27, MVP-282–294): Prüfmittel, Eichung und Kalibrierung.
 * Prüfprofile sind Katalogdaten (P1: organization_id NULL = globale Vorlage,
 * Org-Zeilen überschreiben); Prüfprotokolle/Zertifikate sind unveränderbare
 * Nachweise (append-only, Korrektur versioniert). Einsatzsperren und
 * Ausnahmefreigaben laufen über das GEMEINSAME Sperrmodell asset_blocks/
 * asset_block_exceptions (D12) — die in der Feature-Doku genannten Entitäten
 * asset_compliance_blocks/asset_compliance_exceptions werden dadurch erfüllt.
 * Die Normen-Referenzmatrix (MVP-293) trägt frame_version (P3/W12) und macht
 * keine Konformitätszusage.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('asset_compliance_profiles', function (Blueprint $table): void {
            $table->id();
            // P1-Katalog: NULL = globale Vorlage, sonst Org-Profil
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->string('inspection_kind', 30);
            $table->unsignedInteger('interval_months');
            $table->unsignedInteger('warn_days_before')->default(30);
            $table->unsignedInteger('tolerance_days')->default(0);
            $table->unsignedInteger('grace_days')->default(0);
            $table->string('blocking_mode', 30)->default('warn');
            $table->boolean('requires_certificate')->default(false);
            $table->string('default_authority')->nullable();
            $table->text('description')->nullable();
            // P3/W12: Regelwerks-Rahmenversion des Profils
            $table->string('frame_version', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'ac_profiles_org_code_unique');
        });

        Schema::create('asset_compliance_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('asset_compliance_profile_id')->constrained('asset_compliance_profiles', indexName: 'ac_requirements_profile_fk')->cascadeOnDelete();
            $table->string('code', 60)->nullable();
            $table->string('label');
            $table->string('unit', 30)->nullable();
            $table->decimal('limit_min', 14, 4)->nullable();
            $table->decimal('limit_max', 14, 4)->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->index(['asset_compliance_profile_id'], 'ac_requirements_profile_idx');
        });

        Schema::create('asset_compliance_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_compliance_profile_id')->constrained('asset_compliance_profiles', indexName: 'ac_assignments_profile_fk')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('interval_months_override')->nullable();
            $table->date('last_done_on')->nullable();
            $table->date('next_due_on')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Externe Prüfstelle (Feature 033: external_contacts)
            $table->foreignId('external_contact_id')->nullable()->constrained('external_contacts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'asset_compliance_profile_id'], 'ac_assignments_asset_profile_unique');
            $table->index(['organization_id', 'next_due_on'], 'ac_assignments_org_due_idx');
        });

        Schema::create('asset_inspection_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_compliance_assignment_id')->constrained('asset_compliance_assignments', indexName: 'ac_schedules_assignment_fk')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->date('due_on');
            $table->date('planned_on')->nullable();
            $table->foreignId('inspector_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('external_contact_id')->nullable()->constrained('external_contacts')->nullOnDelete();
            $table->string('status', 20)->default('planned');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'due_on'], 'ac_schedules_org_status_idx');
        });

        Schema::create('asset_inspection_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_inspection_schedule_id')->nullable()->constrained('asset_inspection_schedules', indexName: 'ac_events_schedule_fk')->nullOnDelete();
            $table->foreignId('asset_compliance_assignment_id')->nullable()->constrained('asset_compliance_assignments', indexName: 'ac_events_assignment_fk')->nullOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->dateTime('performed_at');
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_inspector_name')->nullable();
            $table->string('result', 30);
            $table->date('valid_until')->nullable();
            $table->json('checklist')->nullable();
            $table->string('signature_name')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->text('note')->nullable();
            // Versionierte Korrektur statt Änderung (Nachweise unveränderbar)
            $table->foreignId('supersedes_id')->nullable()->constrained('asset_inspection_events', indexName: 'ac_events_supersedes_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'asset_id', 'performed_at'], 'ac_events_org_asset_idx');
        });

        Schema::create('asset_inspection_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_inspection_event_id')->constrained('asset_inspection_events', indexName: 'ac_results_event_fk')->cascadeOnDelete();
            $table->foreignId('asset_compliance_requirement_id')->nullable()->constrained('asset_compliance_requirements', indexName: 'ac_results_requirement_fk')->nullOnDelete();
            $table->string('label');
            $table->decimal('value', 14, 4)->nullable();
            $table->string('unit', 30)->nullable();
            // P2-Snapshot der Grenzwerte zum Prüfzeitpunkt
            $table->decimal('limit_min', 14, 4)->nullable();
            $table->decimal('limit_max', 14, 4)->nullable();
            $table->boolean('passed')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['asset_inspection_event_id'], 'ac_results_event_idx');
        });

        Schema::create('asset_measurement_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_inspection_event_id')->constrained('asset_inspection_events', indexName: 'ac_measurements_event_fk')->cascadeOnDelete();
            $table->string('label');
            $table->decimal('value', 14, 4);
            $table->string('unit', 30)->nullable();
            $table->dateTime('measured_at')->nullable();
            $table->timestamps();

            $table->index(['asset_inspection_event_id'], 'ac_measurements_event_idx');
        });

        Schema::create('asset_calibration_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_inspection_event_id')->constrained('asset_inspection_events', indexName: 'ac_certificates_event_fk')->cascadeOnDelete();
            $table->string('certificate_no');
            $table->string('issuer');
            $table->date('issued_on');
            $table->date('valid_until')->nullable();
            $table->string('measurement_range')->nullable();
            $table->string('tolerance')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sha256', 64)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'certificate_no'], 'ac_certificates_org_no_idx');
        });

        Schema::create('asset_compliance_norm_references', function (Blueprint $table): void {
            $table->id();
            // P1: NULL = globaler Katalogeintrag, Org-Zeile = Override
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('inspection_kind', 30);
            $table->string('jurisdiction', 10)->default('DE');
            $table->string('norm_label');
            $table->string('source_url')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('frame_version', 40)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['inspection_kind', 'jurisdiction'], 'ac_norms_kind_jurisdiction_idx');
        });

        Schema::create('asset_compliance_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('asset_compliance_report_snapshots');
        Schema::dropIfExists('asset_compliance_norm_references');
        Schema::dropIfExists('asset_calibration_certificates');
        Schema::dropIfExists('asset_measurement_values');
        Schema::dropIfExists('asset_inspection_results');
        Schema::dropIfExists('asset_inspection_events');
        Schema::dropIfExists('asset_inspection_schedules');
        Schema::dropIfExists('asset_compliance_assignments');
        Schema::dropIfExists('asset_compliance_requirements');
        Schema::dropIfExists('asset_compliance_profiles');
    }
};
