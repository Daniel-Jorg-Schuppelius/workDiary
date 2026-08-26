<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_101900_add_logbook_fields_to_vehicles_and_travel_logs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Steuerlich anerkanntes Fahrtenbuch (Feature 137, MVP-702; Vollscan
 * 2026-08-23, H13): Fahrtenbuch-Modus je Fahrzeug, km-Stände/Fahrtart je
 * Fahrt, Festschreibung (locked_at) und Stornofahrt mit Referenz + Grund.
 * Erstattungs-Fahrten (logbook_mode=false) bleiben unverändert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->boolean('logbook_mode')->default(false)->after('odometer_km');
        });

        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->unsignedInteger('odometer_start_km')->nullable()->after('distance_km');
            $table->unsignedInteger('odometer_end_km')->nullable()->after('odometer_start_km');
            $table->string('trip_kind', 16)->default('business')->after('odometer_end_km');
            $table->timestamp('locked_at')->nullable()->after('notes');
            $table->foreignId('corrects_travel_log_id')->nullable()->after('locked_at')
                ->constrained('travel_logs')->nullOnDelete();
            $table->string('correction_reason', 255)->nullable()->after('corrects_travel_log_id');

            // km-Kette je Fahrzeug: letzte Fahrt nach Datum/End-km.
            $table->index(['vehicle_id', 'date', 'odometer_end_km'], 'travel_logs_vehicle_chain_idx');
        });
    }

    public function down(): void {
        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->dropIndex('travel_logs_vehicle_chain_idx');
            $table->dropForeign(['corrects_travel_log_id']);
            $table->dropColumn(['odometer_start_km', 'odometer_end_km', 'trip_kind', 'locked_at', 'corrects_travel_log_id', 'correction_reason']);
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn('logbook_mode');
        });
    }
};
