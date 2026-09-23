<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_100700_create_club_exam_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prüfungen und Gradvergabe (Feature 159, MVP-847): ein Prüfungsangebot je
 * Termin mit eingefrorener Regelversion und Zielgraden, Kandidaten mit
 * Zulassungsbericht, fachlicher Freigabe, Ausnahme und Ergebnis; die
 * verwendeten Nachweise bleiben je Kandidat referenziert, die Gradvergabe
 * verweist auf den Kandidaten.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_exam_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_grading_system_id')->constrained('club_grading_systems')->cascadeOnDelete();
            // Eingefroren bei Anlage: Regeländerungen gelten für neue Angebote.
            $table->foreignId('club_grading_version_id')->constrained('club_grading_versions')->restrictOnDelete();
            $table->json('examiner_user_ids')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('results_released_at')->nullable();
            $table->timestamps();

            $table->unique('event_id', 'club_exam_offer_event_uq');
        });

        Schema::create('club_exam_offer_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_exam_offer_id')->constrained('club_exam_offers')->cascadeOnDelete();
            $table->foreignId('club_grade_id')->constrained('club_grades')->cascadeOnDelete();

            $table->unique(['club_exam_offer_id', 'club_grade_id'], 'club_exam_offer_grade_uq');
        });

        Schema::create('club_exam_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_exam_offer_id')->constrained('club_exam_offers')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->foreignId('target_grade_id')->constrained('club_grades')->restrictOnDelete();
            $table->string('status', 16)->default('requested');
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('admitted_at')->nullable();
            $table->foreignId('admitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Fachliche Freigabe der Leitung (nur wenn die Voraussetzung sie verlangt).
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Ausnahmezulassung: nur wenn die Regel sie erlaubt, mit Recht und Begründung.
            $table->string('exception_reason', 255)->nullable();
            $table->foreignId('exception_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('eligibility_met')->nullable();
            $table->json('eligibility_report')->nullable();
            $table->timestamp('checked_at')->nullable();
            // Fachliche Überprüfung nötig: Verschiebung oder Nachweiskorrektur nach der Zulassung.
            $table->timestamp('review_required_at')->nullable();
            $table->string('review_note', 255)->nullable();
            $table->timestamp('result_recorded_at')->nullable();
            $table->foreignId('result_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result_note', 255)->nullable();
            $table->foreignId('awarded_member_grade_id')->nullable()->constrained('club_member_grades')->nullOnDelete();
            $table->timestamps();

            $table->unique(['club_exam_offer_id', 'club_member_id'], 'club_exam_cand_offer_member_uq');
            $table->index(['club_member_id', 'status'], 'club_exam_cand_member_status_idx');
        });

        Schema::create('club_exam_candidate_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_exam_candidate_id')->constrained('club_exam_candidates')->cascadeOnDelete();
            $table->foreignId('club_attendance_record_id')->constrained('club_attendance_records')->cascadeOnDelete();

            $table->unique(['club_exam_candidate_id', 'club_attendance_record_id'], 'club_exam_cand_record_uq');
        });

        Schema::table('club_member_grades', function (Blueprint $table): void {
            $table->foreignId('club_exam_candidate_id')->nullable()->after('source')->constrained('club_exam_candidates')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('club_member_grades', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('club_exam_candidate_id');
        });
        Schema::dropIfExists('club_exam_candidate_records');
        Schema::dropIfExists('club_exam_candidates');
        Schema::dropIfExists('club_exam_offer_grades');
        Schema::dropIfExists('club_exam_offers');
    }
};
