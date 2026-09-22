<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_100300_create_club_event_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vereinstermine (Feature 159, MVP-843): Vereinsdetails am vorhandenen
 * `Event` (kein zweiter Kalender), Zielgruppen je Termin und die typisierte
 * Mitgliedsteilnahme mit Anmeldung/Warteliste — getrennt vom Benutzer-Pivot
 * `event_user`, das Personal-/Leitungszuordnungen weiter führt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_event_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('kind', 16)->default('training'); // training|course|exam|meeting|other
            $table->string('visibility', 16)->default('groups'); // club|groups|invited
            $table->foreignId('club_department_id')->nullable()->constrained('club_departments')->nullOnDelete();
            // Fristen relativ zum Beginn (Stunden) — serienfest, wie LearningUnit.
            $table->unsignedSmallInteger('registration_lead_hours')->nullable();
            $table->unsignedSmallInteger('cancellation_lead_hours')->nullable();
            $table->timestamps();

            $table->unique('event_id', 'club_ev_det_event_uq');
            $table->index(['organization_id', 'kind'], 'club_ev_det_org_kind_idx');
        });

        Schema::create('club_event_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_group_id')->constrained('club_groups')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'club_group_id'], 'club_ev_grp_event_group_uq');
            $table->index(['club_group_id', 'event_id'], 'club_ev_grp_group_event_idx');
        });

        Schema::create('club_event_participations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->string('status', 16); // invited|registered|waitlisted|cancelled
            $table->string('source', 16); // admin|leader|guardian|self|spontaneous|invitation
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('club_guardian_id')->nullable()->constrained('club_guardians')->nullOnDelete();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            // Genau eine Teilnahme je Termin und Mitglied — Doppelteilnahmen sind ausgeschlossen.
            $table->unique(['event_id', 'club_member_id'], 'club_ev_part_event_member_uq');
            $table->index(['event_id', 'status', 'registered_at'], 'club_ev_part_event_status_idx');
            $table->index(['club_member_id', 'status'], 'club_ev_part_member_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('club_event_participations');
        Schema::dropIfExists('club_event_groups');
        Schema::dropIfExists('club_event_details');
    }
};
