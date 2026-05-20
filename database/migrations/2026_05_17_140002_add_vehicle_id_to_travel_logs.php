<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_140002_add_vehicle_id_to_travel_logs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional link from a TravelLog row to a concrete fleet vehicle.
 * When set, TravelLogService.applyDefaults() prefers the vehicle's
 * `default_rate_per_km` over the per-enum config rate.
 *
 * The pre-existing string `vehicle` column stays as a fallback / type
 * label so old rows keep working without migration of historical data.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->foreignId('vehicle_id')->nullable()->after('attendance_id')
                ->constrained('vehicles')->nullOnDelete();
            $table->index('vehicle_id');
        });
    }

    public function down(): void {
        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->dropForeign(['vehicle_id']);
            $table->dropIndex(['vehicle_id']);
            $table->dropColumn('vehicle_id');
        });
    }
};
