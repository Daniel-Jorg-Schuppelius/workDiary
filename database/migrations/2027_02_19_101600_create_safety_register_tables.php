<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_101600_create_safety_register_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arbeitsschutz-Register (Feature 132, MVP-697; Vollscan 2026-08-23, H3):
 * Gefährdungsbeurteilung (§ 5 ArbSchG) mit Positionen und Versionskette,
 * Unterweisung (DGUV V1 § 4) mit Teilnahme-Nachweis je Person sowie
 * arbeitsmedizinische Vorsorge (ArbMedVV) ohne Gesundheitsdaten.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('hazard_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Laufende Nummer je Org — bleibt über Versionen stabil (GB-3 v1, v2, …).
            $table->unsignedInteger('assessment_no');
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('hazard_assessments')->nullOnDelete();
            $table->string('area', 180);
            $table->string('activity', 180)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 16)->default('draft'); // draft|inReview|approved|archived
            $table->date('review_due_on')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'assessment_no', 'version'], 'hazard_assess_org_no_ver_uq');
            $table->index(['organization_id', 'status', 'review_due_on'], 'hazard_assess_org_status_idx');
        });

        Schema::create('hazard_assessment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('hazard_assessment_id')->constrained('hazard_assessments')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('hazard', 255);
            $table->text('measure')->nullable();
            // Risiko = Schwere × Wahrscheinlichkeit (je 1–5), Produkt persistiert.
            $table->unsignedTinyInteger('severity_before');
            $table->unsignedTinyInteger('likelihood_before');
            $table->unsignedSmallInteger('risk_before');
            $table->unsignedTinyInteger('severity_after')->nullable();
            $table->unsignedTinyInteger('likelihood_after')->nullable();
            $table->unsignedSmallInteger('risk_after')->nullable();
            $table->timestamps();

            $table->index(['hazard_assessment_id', 'position'], 'hazard_item_assess_pos_idx');
        });

        Schema::create('safety_instructions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedInteger('instruction_no');
            $table->string('topic', 180);
            $table->foreignId('hazard_assessment_id')->nullable()->constrained('hazard_assessments')->nullOnDelete();
            $table->date('held_on');
            $table->foreignId('instructor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('repeat_interval_months')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'instruction_no'], 'safety_instr_org_no_uq');
            $table->index(['organization_id', 'held_on'], 'safety_instr_org_held_idx');
        });

        Schema::create('safety_instruction_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('safety_instruction_id')->constrained('safety_instructions')->cascadeOnDelete();
            // Nachweis-Zeile: RESTRICT wie die übrigen Nachweis-Tabellen (F4) —
            // Austritt läuft über den Offboarding-Workflow, nicht per Hard-Delete.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('signer_name', 120)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('method', 20)->nullable(); // confirmed|drawn
            $table->string('signature_image_path', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('hash', 64)->nullable();
            $table->date('next_due_on')->nullable();
            $table->timestamps();

            $table->unique(['safety_instruction_id', 'user_id'], 'safety_instr_part_uq');
            $table->index(['organization_id', 'user_id', 'next_due_on'], 'safety_instr_part_due_idx');
        });

        Schema::create('medical_checkups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16); // mandatory|offered|requested
            $table->string('occasion', 180)->nullable();
            $table->date('performed_on');
            $table->date('next_due_on')->nullable();
            $table->boolean('certificate_on_file')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'user_id', 'next_due_on'], 'medical_checkup_org_user_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('medical_checkups');
        Schema::dropIfExists('safety_instruction_participants');
        Schema::dropIfExists('safety_instructions');
        Schema::dropIfExists('hazard_assessment_items');
        Schema::dropIfExists('hazard_assessments');
    }
};
