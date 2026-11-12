<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_240000_add_responsibility_and_deactivation_to_patrols.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wächterrundgänge (MVP-1611): Verantwortliche der Route für Abweichungen,
 * Deaktivieren von Kontrollpunkten ohne Wirkung auf frühere Läufe und
 * Merker für die einmalige Meldung eines nicht beendeten Rundgangs.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('patrol_routes', function (Blueprint $table): void {
            $table->foreignId('responsible_user_id')->nullable()->after('site_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('patrol_checkpoints', function (Blueprint $table): void {
            $table->timestamp('deactivated_at')->nullable()->after('tolerance_minutes');
        });

        Schema::table('patrol_runs', function (Blueprint $table): void {
            $table->timestamp('overdue_flagged_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void {
        Schema::table('patrol_runs', function (Blueprint $table): void {
            $table->dropColumn('overdue_flagged_at');
        });

        Schema::table('patrol_checkpoints', function (Blueprint $table): void {
            $table->dropColumn('deactivated_at');
        });

        Schema::table('patrol_routes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsible_user_id');
        });
    }
};
