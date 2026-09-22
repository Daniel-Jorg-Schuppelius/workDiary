<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_100200_create_club_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vereinsbasis (Feature 159, MVP-842): Mitgliederstamm ohne Loginpflicht,
 * Mitgliedschaftsverlauf, Abteilungen, Gruppen mit Alterskriterien,
 * Gruppenzuordnungen mit Gültigkeit, Wechsel-/Prüfvorschläge und
 * Vertretungen (Sorgeberechtigte).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            // Disziplin für Graduierungsordnungen (MVP-846); ohne Wert eine gewöhnliche Abteilung.
            $table->string('discipline', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'sort_order'], 'club_dep_org_sort_idx');
        });

        Schema::create('club_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Laufende Nummer je Organisation — Abgleichschlüssel des CSV-Imports.
            $table->unsignedInteger('member_no');
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('email', 190)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('street', 190)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 120)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('kind', 16)->default('active'); // active|passive|supporting|paused
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'member_no'], 'club_member_org_no_uq');
            $table->index(['organization_id', 'last_name', 'first_name'], 'club_member_org_name_idx');
            $table->index(['organization_id', 'user_id'], 'club_member_org_user_idx');
            $table->index(['organization_id', 'birth_date'], 'club_member_org_birth_idx');
        });

        Schema::create('club_membership_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->string('kind', 16); // active|passive|supporting|paused
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['club_member_id', 'starts_on'], 'club_period_member_start_idx');
        });

        Schema::create('club_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_department_id')->nullable()->constrained('club_departments')->nullOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->foreignId('leader_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('max_members')->nullable();
            $table->string('admission_mode', 16)->default('leader'); // leader|application
            // Inklusive Grenzen, leer = offen; Vergleich am Stichtag (Eintritt bzw. Prüfung).
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->string('criteria_note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'club_department_id', 'name'], 'club_group_org_dep_name_idx');
            $table->index(['organization_id', 'leader_user_id'], 'club_group_org_leader_idx');
        });

        Schema::create('club_group_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_group_id')->constrained('club_groups')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->string('status', 16); // requested|active|ended|rejected
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['club_group_id', 'status', 'valid_from'], 'club_gm_group_status_idx');
            $table->index(['club_member_id', 'status'], 'club_gm_member_status_idx');
        });

        Schema::create('club_group_change_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->foreignId('club_group_id')->constrained('club_groups')->cascadeOnDelete();
            $table->string('reason', 24); // age_below|age_above|review_required
            $table->string('status', 16)->default('open'); // open|confirmed|dismissed
            $table->foreignId('suggested_group_id')->nullable()->constrained('club_groups')->nullOnDelete();
            $table->date('effective_on')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['club_group_id', 'status'], 'club_gcp_group_status_idx');
            $table->index(['club_member_id', 'status'], 'club_gcp_member_status_idx');
        });

        Schema::create('club_guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('email', 190)->nullable();
            $table->string('phone', 60)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('permissions')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id'], 'club_guardian_org_user_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('club_guardians');
        Schema::dropIfExists('club_group_change_proposals');
        Schema::dropIfExists('club_group_memberships');
        Schema::dropIfExists('club_groups');
        Schema::dropIfExists('club_membership_periods');
        Schema::dropIfExists('club_members');
        Schema::dropIfExists('club_departments');
    }
};
