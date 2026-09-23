<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_100600_create_club_grading_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optionales Graduierungsmodul (Feature 159, MVP-846): versionierte Ordnungen
 * je Disziplin mit geordneten Graden, Voraussetzungen je Zielgrad als
 * UND-Liste, vergebene/anerkannte Grade je Mitglied, Lehrgangs- und externe
 * Trainingsnachweise; Gruppen und Termine erhalten Disziplin bzw. Gradkriterien.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_grading_systems', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('discipline', 60);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'discipline'], 'club_grading_sys_org_disc_idx');
        });

        Schema::create('club_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_grading_system_id')->constrained('club_grading_systems')->cascadeOnDelete();
            $table->string('name', 80);
            // Reihenfolge ist Fachdatum; Farbe nur Darstellung.
            $table->unsignedSmallInteger('rank');
            $table->string('color', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['club_grading_system_id', 'name'], 'club_grades_system_name_uq');
            $table->index(['club_grading_system_id', 'rank'], 'club_grades_system_rank_idx');
        });

        Schema::create('club_grading_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_grading_system_id')->constrained('club_grading_systems')->cascadeOnDelete();
            $table->unsignedSmallInteger('version_no');
            $table->string('status', 16)->default('draft'); // draft|active|superseded
            $table->date('valid_from')->nullable();
            // Feste Unterrichtseinheit in Minuten (z. B. 45); Rest bleibt erhalten, Rundung erst in der Anzeige.
            $table->unsignedSmallInteger('unit_minutes')->nullable();
            $table->boolean('accepts_external_credits')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['club_grading_system_id', 'version_no'], 'club_grading_ver_system_no_uq');
        });

        Schema::create('club_grade_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_grading_version_id')->constrained('club_grading_versions')->cascadeOnDelete();
            $table->foreignId('club_grade_id')->constrained('club_grades')->cascadeOnDelete();
            $table->foreignId('previous_grade_id')->nullable()->constrained('club_grades')->nullOnDelete();
            $table->unsignedInteger('min_minutes')->nullable();
            $table->unsignedSmallInteger('min_sessions')->nullable();
            $table->unsignedSmallInteger('min_minutes_per_session')->nullable();
            $table->string('counting_basis', 32)->default('since_previous_grade');
            $table->unsignedSmallInteger('window_months')->nullable();
            $table->unsignedSmallInteger('wait_months')->nullable();
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->json('counted_event_kinds')->nullable(); // null = Training + Lehrgang
            $table->json('counted_group_ids')->nullable(); // null = alle Gruppen
            $table->string('required_proof_label', 120)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('allows_exception')->default(false);
            $table->timestamps();

            $table->unique(['club_grading_version_id', 'club_grade_id'], 'club_grade_req_version_grade_uq');
        });

        Schema::create('club_member_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->foreignId('club_grading_system_id')->constrained('club_grading_systems')->cascadeOnDelete();
            $table->foreignId('club_grade_id')->constrained('club_grades')->cascadeOnDelete();
            $table->date('obtained_on');
            $table->string('source', 16); // exam|recognized
            $table->string('evidence', 255)->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['club_member_id', 'club_grading_system_id', 'obtained_on'], 'club_member_grades_member_sys_idx');
        });

        Schema::create('club_member_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->string('kind', 24); // course|external_training
            $table->string('label', 120);
            $table->string('discipline', 60)->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->unsignedSmallInteger('sessions')->nullable();
            $table->date('obtained_on');
            $table->date('valid_until')->nullable();
            $table->string('origin', 160)->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['club_member_id', 'kind'], 'club_member_proofs_member_kind_idx');
        });

        Schema::table('club_groups', function (Blueprint $table): void {
            $table->string('discipline', 60)->nullable()->after('criteria_note');
            $table->foreignId('club_grading_system_id')->nullable()->after('discipline')->constrained('club_grading_systems')->nullOnDelete();
            $table->foreignId('min_grade_id')->nullable()->after('club_grading_system_id')->constrained('club_grades')->nullOnDelete();
            $table->foreignId('max_grade_id')->nullable()->after('min_grade_id')->constrained('club_grades')->nullOnDelete();
        });

        Schema::table('club_event_details', function (Blueprint $table): void {
            $table->string('discipline', 60)->nullable()->after('club_department_id');
        });
    }

    public function down(): void {
        Schema::table('club_event_details', function (Blueprint $table): void {
            $table->dropColumn('discipline');
        });
        Schema::table('club_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('max_grade_id');
            $table->dropConstrainedForeignId('min_grade_id');
            $table->dropConstrainedForeignId('club_grading_system_id');
            $table->dropColumn('discipline');
        });
        Schema::dropIfExists('club_member_proofs');
        Schema::dropIfExists('club_member_grades');
        Schema::dropIfExists('club_grade_requirements');
        Schema::dropIfExists('club_grading_versions');
        Schema::dropIfExists('club_grades');
        Schema::dropIfExists('club_grading_systems');
    }
};
