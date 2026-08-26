<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_103400_create_training_management_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trainingsmanagement (Feature 145, MVP-727; Vollscan 2026-08-23, G19):
 * Schulungskatalog mit Kursversionen, Pflichtmatrix Rolle/Team × Kurs und
 * die daraus erzeugten Soll-Einträge je Mitarbeitendem.
 *
 * Bewusst OHNE eigene Nachweis-Tabelle: der Nachweis bleibt der
 * Unterweisungs-Teilnehmer des Arbeitsschutz-Registers (Feature 132) —
 * `safety_instructions` bekommt dafür nur den Kurs-/Versionsbezug, und der
 * Soll-Eintrag zeigt auf die Teilnehmerzeile. Kein Duplikat der Signatur.
 *
 * Kein SoftDelete: die Unique-Schlüssel (Kurscode je Org, Soll je Person ×
 * Kurs) müssten sonst gelöschte Zeilen mitzählen (bekannte 1062-Falle);
 * Ausmustern läuft über `is_active`, echtes Löschen ist im Service an
 * „kein Nachweis vorhanden" gebunden.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('training_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Fachlicher Code je Org — Anker für idempotente Profil-Vorschläge.
            $table->string('code', 60);
            $table->string('title', 180);
            $table->string('provider_kind', 12)->default('internal'); // internal|external
            $table->string('provider_name', 180)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            // Gültigkeit in Monaten: Grundlage der Wiederholungsfälligkeit.
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->string('legal_basis', 180)->nullable();
            // Kosten rein informativ (keine Buchung, kein Beleg) — Feature 145.
            $table->decimal('cost_amount', 12, 2)->nullable();
            $table->string('cost_currency', 3)->nullable();
            // Vorlauf der Fälligkeitsmeldung je Kurs (Scan-Fenster).
            $table->unsignedSmallInteger('lead_days')->default(30);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source', 12)->default('manual'); // manual|profile
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'training_course_org_code_uq');
            $table->index(['organization_id', 'is_active'], 'training_course_org_active_idx');
        });

        Schema::create('training_course_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('training_course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('label', 60)->nullable();
            $table->date('valid_from')->nullable();
            $table->text('content_summary')->nullable();
            // Genau eine aktuelle Version je Kurs (Service hält das durch).
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['training_course_id', 'version'], 'training_course_ver_uq');
            $table->index(['organization_id', 'training_course_id'], 'training_course_ver_org_idx');
        });

        Schema::create('training_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('training_course_id')->constrained('training_courses')->cascadeOnDelete();
            // Zielgruppe: role = UserRole-Slug, team = teams.id (als String,
            // damit ein Unique über beide Arten ohne NULL-Sentinel auskommt).
            $table->string('subject_kind', 10); // role|team
            $table->string('subject_key', 60);
            // Erst-Termin: Tage ab Entstehen des Soll-Eintrags.
            $table->unsignedSmallInteger('first_due_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->string('source', 12)->default('manual'); // manual|profile
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'training_course_id', 'subject_kind', 'subject_key'], 'training_req_uq');
            $table->index(['organization_id', 'subject_kind', 'subject_key'], 'training_req_subject_idx');
        });

        Schema::create('training_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('training_course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->foreignId('training_requirement_id')->nullable()->constrained('training_requirements')->nullOnDelete();
            $table->string('source', 12)->default('requirement'); // requirement|manual
            // Aktueller Soll-Termin; NULL = erfüllt und keine Wiederholung.
            $table->date('due_at')->nullable();
            // due_at abzüglich Kurs-Vorlauf — der Scan vergleicht nur Datum
            // gegen Datum (kein SQL-Datumsrechnen, treiberneutral).
            $table->date('notify_from')->nullable();
            $table->date('fulfilled_at')->nullable();
            // Nachweis = Zeile im Arbeitsschutz-Register (Feature 132).
            $table->foreignId('fulfilled_participant_id')->nullable()->constrained('safety_instruction_participants')->nullOnDelete();
            $table->foreignId('fulfilled_instruction_id')->nullable()->constrained('safety_instructions')->nullOnDelete();
            $table->unsignedSmallInteger('fulfilled_course_version')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'training_course_id'], 'training_assign_user_course_uq');
            $table->index(['organization_id', 'notify_from'], 'training_assign_notify_idx');
            $table->index(['organization_id', 'due_at'], 'training_assign_due_idx');
        });

        // Kursbezug der Unterweisung: erst dadurch wird die Teilnahme im
        // Register zum Nachweis für ein Trainings-Soll (keine Kopie).
        Schema::table('safety_instructions', function (Blueprint $table): void {
            $table->foreignId('training_course_id')->nullable()->after('hazard_assessment_id')->constrained('training_courses')->nullOnDelete();
            $table->foreignId('training_course_version_id')->nullable()->after('training_course_id')->constrained('training_course_versions')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('safety_instructions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('training_course_version_id');
            $table->dropConstrainedForeignId('training_course_id');
        });

        Schema::dropIfExists('training_assignments');
        Schema::dropIfExists('training_requirements');
        Schema::dropIfExists('training_course_versions');
        Schema::dropIfExists('training_courses');
    }
};
