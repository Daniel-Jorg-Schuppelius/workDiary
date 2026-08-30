<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_105600_create_learning_scorm_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fremdinhalte: SCORM und xAPI (Feature 149, MVP-743).
 *
 * Zugekaufte Unterweisungen (Datenschutz, Arbeitssicherheit, Compliance)
 * kommen als SCORM-Paket — daran führt kein Weg vorbei: SCORM 2004 ist seit
 * 2009 eingefroren und trotzdem das Austauschformat, das jedes große LMS
 * importiert.
 *
 * `suspend_data` ist der Platz, an dem ein Inhalt seinen eigenen
 * Zwischenstand ablegt (Seite 7 von 12, Antwort C markiert). Ohne ihn
 * beginnt jede Unterbrechung von vorn — deshalb `longText` statt einer
 * knappen Spalte.
 *
 * xAPI-Statements werden **roh archiviert**: was ein fremder Inhalt sendet,
 * darf nicht durch unsere Interpretation verloren gehen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_scorm_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_unit_id')->constrained('learning_units')->cascadeOnDelete();
            $table->string('title', 180);
            // scorm_1_2 | scorm_2004
            $table->string('version', 12);
            // Ablagepfad des entpackten Pakets (eigener Ordner je Paket).
            $table->string('storage_path', 500);
            $table->string('launch_href', 500)->nullable();
            $table->string('manifest_hash', 64);
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('learning_unit_id', 'lrn_scorm_unit_uq');
            $table->index(['organization_id'], 'lrn_scorm_org_idx');
        });

        Schema::create('learning_scorm_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_scorm_package_id')->constrained('learning_scorm_packages')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            // Rohwerte des Datenmodells, wie der Inhalt sie meldet.
            $table->string('lesson_status', 20)->nullable();
            $table->string('success_status', 20)->nullable();
            $table->decimal('score_scaled', 5, 4)->nullable();
            $table->longText('suspend_data')->nullable();
            $table->string('location', 500)->nullable();
            $table->unsignedInteger('session_seconds')->default(0);
            $table->timestamp('last_commit_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_scorm_package_id', 'learning_enrollment_id'], 'lrn_scorm_state_uq');
        });

        Schema::create('learning_xapi_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->nullable()->constrained('learning_enrollments')->nullOnDelete();
            $table->uuid('statement_id')->nullable();
            $table->string('verb', 255)->nullable();
            $table->string('object_id', 500)->nullable();
            // Vollständiges Statement — roh, nicht interpretiert.
            $table->longText('payload');
            $table->timestamp('stored_at');
            $table->timestamps();

            $table->unique(['organization_id', 'statement_id'], 'lrn_xapi_stmt_uq');
            $table->index(['learning_enrollment_id', 'stored_at'], 'lrn_xapi_enr_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_xapi_statements');
        Schema::dropIfExists('learning_scorm_states');
        Schema::dropIfExists('learning_scorm_packages');
    }
};
