<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_21_100000_add_route_legs_to_tours_and_tour_to_travel_logs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 146, MVP-1172: Routen-Abschnitte (Distanz und Dauer je Bein) an der
 * Tour, damit das Fahrtenbuch Straßenkilometer statt Luftlinie bekommt; die
 * erzeugte Fahrt kennt ihre Tour (einmalige Materialisierung).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('tours', function (Blueprint $table): void {
            $table->json('route_legs')->nullable()->after('route_geometry');
        });
        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->foreignId('tour_id')->nullable()->after('vehicle_id')
                ->constrained('tours', indexName: 'travel_logs_tour_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tour_id');
        });
        Schema::table('tours', function (Blueprint $table): void {
            $table->dropColumn('route_legs');
        });
    }
};
