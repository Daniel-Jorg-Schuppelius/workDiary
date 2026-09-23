<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_101100_create_club_team_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mannschaften und Spielbetrieb (Feature 159, MVP-852): Sportartenprofile
 * als Konfiguration, Saisons und Saisonkader mit Gastspielern, Spieltage als
 * Event-Erweiterung, Terminrollen, Verfügbarkeit, Aufstellungen und
 * Spielplan-Vorschläge aus CSV/ICS.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_sport_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('family', 30);
            $table->json('positions')->nullable();
            $table->unsignedSmallInteger('squad_size_field')->nullable();
            $table->unsignedSmallInteger('squad_size_bench')->nullable();
            $table->string('result_format', 20)->default('goals');
            $table->boolean('has_doubles')->default(false);
            // Stichtag der Altersklasse als MM-TT; leer = Alter am Termintag.
            $table->string('age_cutoff', 5)->nullable();
            $table->json('disciplines')->nullable();
            $table->json('resource_types')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'club_sport_profiles_org_name_uq');
        });

        Schema::create('club_seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'club_seasons_org_name_uq');
        });

        Schema::table('club_departments', function (Blueprint $table): void {
            $table->foreignId('club_sport_profile_id')->nullable()->after('discipline')->constrained('club_sport_profiles')->nullOnDelete();
        });

        Schema::table('club_groups', function (Blueprint $table): void {
            $table->boolean('is_team')->default(false)->after('is_active');
            $table->foreignId('club_sport_profile_id')->nullable()->after('is_team')->constrained('club_sport_profiles')->nullOnDelete();
            $table->string('age_class', 20)->nullable()->after('club_sport_profile_id');
        });

        Schema::create('club_squads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_group_id')->constrained('club_groups')->cascadeOnDelete();
            $table->foreignId('club_season_id')->constrained('club_seasons')->cascadeOnDelete();
            $table->string('name', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['club_group_id', 'club_season_id'], 'club_squads_group_season_uq');
        });

        Schema::create('club_squad_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_squad_id')->constrained('club_squads')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            // Gastspieler: Herkunftsverein; ohne Mitgliedschaft, Beitrag oder Login im eigenen Verein.
            $table->string('guest_origin', 120)->nullable();
            $table->unsignedSmallInteger('jersey_no')->nullable();
            $table->string('position_code', 30)->nullable();
            $table->unsignedSmallInteger('strength_rank')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->unique(['club_squad_id', 'club_member_id', 'valid_from'], 'club_squad_members_uq');
            $table->index(['club_member_id', 'valid_to'], 'club_squad_members_member_idx');
        });

        Schema::create('club_match_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_group_id')->constrained('club_groups')->cascadeOnDelete();
            $table->foreignId('club_squad_id')->nullable()->constrained('club_squads')->nullOnDelete();
            $table->foreignId('club_season_id')->nullable()->constrained('club_seasons')->nullOnDelete();
            $table->string('opponent_name', 150);
            $table->string('competition', 120)->nullable();
            $table->boolean('is_home')->default(true);
            $table->string('venue', 200)->nullable();
            $table->dateTime('meet_at')->nullable();
            $table->string('lineup_status', 20)->default('draft');
            $table->dateTime('lineup_released_at')->nullable();
            $table->foreignId('lineup_released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lineup_conflict_note', 255)->nullable();
            $table->json('result')->nullable();
            $table->string('result_summary', 60)->nullable();
            $table->text('result_note')->nullable();
            $table->dateTime('result_recorded_at')->nullable();
            $table->foreignId('result_recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['club_group_id', 'club_season_id'], 'club_match_details_group_season_idx');
        });

        Schema::create('club_event_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('role', 30);
            $table->foreignId('club_member_id')->nullable()->constrained('club_members')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['event_id', 'role'], 'club_event_roles_event_role_idx');
        });

        Schema::create('club_match_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('note', 255)->nullable();
            $table->dateTime('responded_at');
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'club_member_id'], 'club_match_availabilities_uq');
        });

        Schema::create('club_lineup_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->string('slot', 20);
            // team (Feld/Bank), single, double — eine Person je Schlüssel, Einzel + Doppel bleibt eine Person.
            $table->string('slot_key', 20);
            $table->string('position_code', 30)->nullable();
            $table->unsignedSmallInteger('jersey_no')->nullable();
            $table->unsignedSmallInteger('order_no')->default(0);
            $table->unsignedSmallInteger('pairing_no')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'club_member_id', 'slot_key'], 'club_lineup_entries_uq');
            $table->index(['club_member_id', 'event_id'], 'club_lineup_entries_member_idx');
        });

        Schema::create('club_match_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_group_id')->constrained('club_groups')->cascadeOnDelete();
            $table->string('source', 10);
            $table->string('dedupe_key', 64);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('opponent_name', 150);
            $table->string('competition', 120)->nullable();
            $table->boolean('is_home')->default(true);
            $table->string('venue', 200)->nullable();
            $table->json('raw')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('duplicate_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'dedupe_key'], 'club_match_proposals_org_key_uq');
            $table->index(['club_group_id', 'status'], 'club_match_proposals_group_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('club_match_proposals');
        Schema::dropIfExists('club_lineup_entries');
        Schema::dropIfExists('club_match_availabilities');
        Schema::dropIfExists('club_event_roles');
        Schema::dropIfExists('club_match_details');
        Schema::dropIfExists('club_squad_members');
        Schema::dropIfExists('club_squads');
        Schema::table('club_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('club_sport_profile_id');
            $table->dropColumn(['is_team', 'age_class']);
        });
        Schema::table('club_departments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('club_sport_profile_id');
        });
        Schema::dropIfExists('club_seasons');
        Schema::dropIfExists('club_sport_profiles');
    }
};
