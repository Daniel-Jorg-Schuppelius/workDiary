<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_104500_create_learning_enrollment_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lernplattform: Einschreibung und Fortschritt (Feature 149, MVP-737).
 *
 * Die Einschreibung zeigt auf GENAU EINE Subjektart: `user_id` (interne
 * Mitarbeitende und Portal-Kunden liegen beide in `users`, getrennt wird
 * über den Guard) oder `external_participant_id` (Lernende ohne Konto).
 * Beide gleichzeitig zu setzen ist fachlich sinnlos und wird im Service
 * verhindert; die Unique-Schlüssel decken beide Wege getrennt ab.
 *
 * Eine Einschreibung hängt an einer KURSVERSION, nicht am Kurs: sonst
 * änderte sich der Lernstoff unter laufenden Teilnehmern.
 *
 * Kein SoftDelete — eine Einschreibung ist ein Nachweisvorgang.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
            // Stoffstand dieser Einschreibung (Freigabeversion).
            $table->foreignId('learning_course_version_id')->nullable()->constrained('learning_course_versions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('external_participant_id')->nullable()->constrained('external_participants')->cascadeOnDelete();
            // assigned|in_progress|completed|failed|expired|cancelled
            $table->string('status', 12)->default('assigned');
            // requirement|manual|self|booking|rule — woher die Zuweisung kam.
            $table->string('source', 12)->default('manual');
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_at')->nullable();
            $table->date('access_until')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            // Ergebnis in Prozent (Notenbuch folgt mit MVP-739).
            $table->unsignedTinyInteger('score_percent')->nullable();
            $table->unsignedSmallInteger('points_earned')->default(0);
            $table->timestamps();

            $table->unique(['learning_course_id', 'user_id'], 'lrn_enr_course_user_uq');
            $table->unique(['learning_course_id', 'external_participant_id'], 'lrn_enr_course_ext_uq');
            $table->index(['organization_id', 'status'], 'lrn_enr_org_status_idx');
            $table->index(['organization_id', 'due_at'], 'lrn_enr_org_due_idx');
        });

        Schema::create('learning_enrollment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            $table->string('from_status', 12)->nullable();
            $table->string('to_status', 12);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['learning_enrollment_id', 'created_at'], 'lrn_enr_event_idx');
        });

        Schema::create('learning_unit_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            $table->foreignId('learning_unit_id')->constrained('learning_units')->cascadeOnDelete();
            // open|started|completed
            $table->string('status', 10)->default('open');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            // Fortschritt innerhalb der Einheit (z. B. gesehener Videoanteil in
            // Prozent) — Abschlusskriterium, kein Verhaltensprofil.
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamps();

            $table->unique(['learning_enrollment_id', 'learning_unit_id'], 'lrn_progress_enr_unit_uq');
            $table->index(['organization_id', 'status'], 'lrn_progress_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_unit_progress');
        Schema::dropIfExists('learning_enrollment_events');
        Schema::dropIfExists('learning_enrollments');
    }
};
