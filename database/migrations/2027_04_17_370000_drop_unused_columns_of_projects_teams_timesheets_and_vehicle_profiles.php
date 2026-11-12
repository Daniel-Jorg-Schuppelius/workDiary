<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_370000_drop_unused_columns_of_projects_teams_timesheets_and_vehicle_profiles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1623 (gl-135): Spalten ohne Leser. `projects.global_activities` (Kimai-Rest,
 * Tätigkeiten hängen nie am Projekt), `team_user.is_lead` (Kopie von
 * `teams.lead_user_id`), `timesheets.attendance_total_minutes` und
 * `untracked_minutes` (nie befüllt; den Abgleich mit der Anwesenheit führt der
 * Tagesabschluss), `passenger_vehicle_profiles.large_capacity` (die Kapazität
 * prüft die Disposition über die Sitzplätze).
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('projects', 'global_activities')) {
            Schema::table('projects', function (Blueprint $table): void {
                $table->dropColumn('global_activities');
            });
        }
        if (Schema::hasColumn('team_user', 'is_lead')) {
            Schema::table('team_user', function (Blueprint $table): void {
                $table->dropColumn('is_lead');
            });
        }
        if (Schema::hasColumn('timesheets', 'attendance_total_minutes')) {
            Schema::table('timesheets', function (Blueprint $table): void {
                $table->dropColumn(['attendance_total_minutes', 'untracked_minutes']);
            });
        }
        if (Schema::hasColumn('passenger_vehicle_profiles', 'large_capacity')) {
            Schema::table('passenger_vehicle_profiles', function (Blueprint $table): void {
                $table->dropColumn('large_capacity');
            });
        }
    }

    public function down(): void {
        if (! Schema::hasColumn('projects', 'global_activities')) {
            Schema::table('projects', function (Blueprint $table): void {
                $table->boolean('global_activities')->default(true);
            });
        }
        if (! Schema::hasColumn('team_user', 'is_lead')) {
            Schema::table('team_user', function (Blueprint $table): void {
                $table->boolean('is_lead')->default(false);
            });
        }
        if (! Schema::hasColumn('timesheets', 'attendance_total_minutes')) {
            Schema::table('timesheets', function (Blueprint $table): void {
                $table->unsignedInteger('attendance_total_minutes')->default(0);
                $table->integer('untracked_minutes')->default(0);
            });
        }
        if (! Schema::hasColumn('passenger_vehicle_profiles', 'large_capacity')) {
            Schema::table('passenger_vehicle_profiles', function (Blueprint $table): void {
                $table->boolean('large_capacity')->default(false);
            });
        }
    }
};
