<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_10_120000_add_schedule_type_to_work_schedules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('work_schedules', function (Blueprint $table): void {
            // Arbeitszeit-Typ; Default 'flextime' = bisheriges Verhalten.
            $table->string('schedule_type', 32)->default('flextime')->after('user_id');
            // Pro-Wochentag-Vorgaben (nur Typ per_weekday): ISO-Wochentag => {mode,minutes,start,end,break}.
            $table->json('day_targets')->nullable()->after('working_days');
        });
    }

    public function down(): void {
        Schema::table('work_schedules', function (Blueprint $table): void {
            $table->dropColumn(['schedule_type', 'day_targets']);
        });
    }
};
