<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_104700_create_learning_quiz_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prüfungskern der Lernplattform (Feature 149, MVP-738).
 *
 * Kernidee ist die **revisionssichere Prüfungsakte**: ein Versuch friert
 * die gestellten Fragen samt Optionen als `questions_snapshot` ein. Ohne
 * ihn ließe sich ein Ergebnis nach einer Fragenänderung nicht mehr
 * erklären — und genau das fragt ein Auditor nach einem Unfall.
 *
 * Versuche und Antworten werden **nie gelöscht**; eine nachträgliche
 * Korrektur ist additiv (`corrected_points`, `correction_note`) und
 * überschreibt den ursprünglichen Wert nicht. Deshalb kein SoftDelete.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Prüfung an einer Lerneinheit; freistehende Prüfungen (das
            // LearnDash-„Exam") hängen an keiner Einheit.
            $table->foreignId('learning_unit_id')->nullable()->constrained('learning_units')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('pass_percent')->default(80);
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            // 0 = unbegrenzt viele Versuche.
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->unsignedSmallInteger('retry_wait_hours')->default(0);
            // N aus M: NULL = alle Fragen stellen.
            $table->unsignedSmallInteger('questions_per_attempt')->nullable();
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_answers')->default(true);
            // immediate|end|none — wann die Lösung sichtbar wird.
            $table->string('feedback_mode', 10)->default('end');
            $table->boolean('show_solutions')->default(false);
            $table->timestamps();

            $table->unique('learning_unit_id', 'lrn_quiz_unit_uq');
            $table->index(['organization_id'], 'lrn_quiz_org_idx');
        });

        Schema::create('learning_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_quiz_id')->constrained('learning_quizzes')->cascadeOnDelete();
            // single|multiple|true_false|short_text|cloze|sort|matching|essay
            $table->string('kind', 12);
            $table->text('prompt');
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('points')->default(1);
            $table->unsignedSmallInteger('position')->default(0);
            // Typspezifisches: Musterlösungen, Lückentext, Teilpunkte-Regel.
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['learning_quiz_id', 'position'], 'lrn_question_quiz_pos_idx');
        });

        Schema::create('learning_question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_question_id')->constrained('learning_questions')->cascadeOnDelete();
            $table->string('label', 500);
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            // Zuordnungsfragen: gleicher Schlüssel = zusammengehöriges Paar.
            $table->string('match_key', 60)->nullable();
            $table->timestamps();

            $table->index(['learning_question_id', 'position'], 'lrn_option_question_pos_idx');
        });

        Schema::create('learning_quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_quiz_id')->constrained('learning_quizzes')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            // Eingefrorene Fragen dieses Versuchs — die Prüfungsakte.
            $table->longText('questions_snapshot');
            $table->unsignedSmallInteger('score_points')->default(0);
            $table->unsignedSmallInteger('max_points')->default(0);
            $table->unsignedTinyInteger('score_percent')->nullable();
            $table->boolean('passed')->nullable();
            // Nachweisdaten statt Proctoring: Gerät und Adresse des Starts.
            $table->string('client_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->unique(['learning_enrollment_id', 'learning_quiz_id', 'attempt_no'], 'lrn_attempt_no_uq');
            $table->index(['organization_id', 'submitted_at'], 'lrn_attempt_org_sub_idx');
        });

        Schema::create('learning_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_quiz_attempt_id')->constrained('learning_quiz_attempts')->cascadeOnDelete();
            $table->foreignId('learning_question_id')->constrained('learning_questions')->cascadeOnDelete();
            // Gegebene Antwort je Typ (Optionen-IDs, Text, Reihenfolge, Paare).
            $table->json('payload')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedSmallInteger('points_awarded')->default(0);
            // Nachträgliche Korrektur additiv — der Erstwert bleibt stehen.
            $table->unsignedSmallInteger('corrected_points')->nullable();
            $table->string('correction_note', 500)->nullable();
            $table->foreignId('graded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_quiz_attempt_id', 'learning_question_id'], 'lrn_answer_attempt_q_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_answers');
        Schema::dropIfExists('learning_quiz_attempts');
        Schema::dropIfExists('learning_question_options');
        Schema::dropIfExists('learning_questions');
        Schema::dropIfExists('learning_quizzes');
    }
};
