<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_101400_create_club_competition_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual-, Wettkampf- und Schießsport (Feature 159, MVP-855): Wettkämpfe
 * als Termin mit Disziplinliste, Meldungen je Disziplin, Startrechte als
 * dokumentierte Prüfung, manuell erfasste Leistungen mit Bestätigung und
 * vom Verein konfigurierte Anwesenheitsanforderungen (Nachweisliste).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_competition_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_sport_profile_id')->constrained('club_sport_profiles')->cascadeOnDelete();
            // Angebotene Disziplincodes aus dem Profil.
            $table->json('disciplines');
            $table->string('venue', 200)->nullable();
            $table->string('organizer', 150)->nullable();
            $table->decimal('entry_fee', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('requires_start_right')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('club_competition_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->string('discipline_code', 30);
            $table->string('status', 20)->default('registered');
            $table->string('review_reason', 255)->nullable();
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('registered_at');
            $table->timestamps();
            $table->unique(['event_id', 'club_member_id', 'discipline_code'], 'club_competition_entries_uq');
        });

        Schema::create('club_start_rights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->foreignId('club_sport_profile_id')->nullable()->constrained('club_sport_profiles')->nullOnDelete();
            $table->string('reference', 120)->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['club_member_id', 'club_sport_profile_id'], 'club_start_rights_member_idx');
        });

        Schema::create('club_performances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->foreignId('club_sport_profile_id')->constrained('club_sport_profiles')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('discipline_code', 30);
            $table->date('performed_on');
            $table->decimal('value', 12, 3);
            // Schnappschuss aus dem Profil: Einheit und Vergleichsrichtung zum Erfassungszeitpunkt.
            $table->string('unit', 20)->nullable();
            $table->boolean('lower_is_better')->default(false);
            $table->unsignedSmallInteger('placement')->nullable();
            $table->string('note', 255)->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['club_member_id', 'club_sport_profile_id', 'discipline_code'], 'club_performances_member_idx');
            $table->index(['event_id', 'discipline_code'], 'club_performances_event_idx');
        });

        Schema::create('club_attendance_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('club_group_id')->nullable()->constrained('club_groups')->nullOnDelete();
            $table->foreignId('club_department_id')->nullable()->constrained('club_departments')->nullOnDelete();
            // Vom Verein konfiguriert — keine gesetzlichen Schwellen im Code.
            $table->unsignedSmallInteger('required_count');
            $table->unsignedSmallInteger('period_months');
            $table->string('event_kind', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'club_attendance_requirements_org_name_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('club_attendance_requirements');
        Schema::dropIfExists('club_performances');
        Schema::dropIfExists('club_start_rights');
        Schema::dropIfExists('club_competition_entries');
        Schema::dropIfExists('club_competition_details');
    }
};
