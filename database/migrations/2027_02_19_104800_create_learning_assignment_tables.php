<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_104800_create_learning_assignment_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aufgaben und Bewertung (Feature 149, MVP-739).
 *
 * Eine Aufgabe hängt an einer Lerneinheit (`kind = assignment`); die Abgabe
 * gehört zur Einschreibung. Dateien laufen über die vorhandene
 * Anhang-Mechanik (`HasAttachments`) — es entsteht keine zweite Ablage.
 *
 * Die Rubrik (Bewertungskriterien mit Gewicht) liegt als JSON an der
 * Aufgabe und wird bei der Bewertung **eingefroren** (`rubric_snapshot`):
 * eine später geänderte Rubrik darf alte Bewertungen nicht umdeuten.
 *
 * Kein SoftDelete — eine Abgabe ist ein Nachweisvorgang.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_unit_id')->constrained('learning_units')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('instructions')->nullable();
            // text|file|both — was abgegeben werden muss.
            $table->string('submission_kind', 10)->default('both');
            $table->unsignedSmallInteger('due_days')->nullable();
            $table->unsignedSmallInteger('points')->default(10);
            $table->unsignedTinyInteger('pass_percent')->default(50);
            // Kriterien: [{key, label, weight, max_points}]
            $table->json('rubric')->nullable();
            // Zweitbewertung für zertifizierungsrelevante Kurse.
            $table->boolean('requires_second_opinion')->default(false);
            $table->timestamps();

            $table->unique('learning_unit_id', 'lrn_assignment_unit_uq');
            $table->index(['organization_id'], 'lrn_assignment_org_idx');
        });

        Schema::create('learning_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_assignment_id')->constrained('learning_assignments')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            // draft|submitted|returned|graded
            $table->string('status', 10)->default('draft');
            $table->text('body')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('points_awarded')->nullable();
            $table->unsignedTinyInteger('score_percent')->nullable();
            $table->boolean('passed')->nullable();
            $table->text('feedback')->nullable();
            // Kriterien-Punkte + eingefrorene Rubrik der Bewertung.
            $table->json('rubric_scores')->nullable();
            $table->json('rubric_snapshot')->nullable();
            // Zweitbewertung (Vier-Augen) — getrennt von der Erstbewertung.
            $table->foreignId('second_opinion_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('second_opinion_at')->nullable();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->timestamps();

            $table->unique(['learning_assignment_id', 'learning_enrollment_id'], 'lrn_submission_uq');
            $table->index(['organization_id', 'status'], 'lrn_submission_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_submissions');
        Schema::dropIfExists('learning_assignments');
    }
};
