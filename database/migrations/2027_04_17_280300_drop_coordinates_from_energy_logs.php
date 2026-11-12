<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_280300_drop_coordinates_from_energy_logs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1619 (G-08): Der Ort eines Tank- oder Ladevorgangs steht in
 * `location_address`. Koordinaten nahm nur die Validierung an — kein
 * Formular, keine Karte, kein Bericht las sie.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('energy_logs', 'location_lat')) {
            return;
        }
        Schema::table('energy_logs', function (Blueprint $table): void {
            $table->dropColumn(['location_lat', 'location_lng']);
        });
    }

    public function down(): void {
        if (Schema::hasColumn('energy_logs', 'location_lat')) {
            return;
        }
        Schema::table('energy_logs', function (Blueprint $table): void {
            $table->decimal('location_lat', 10, 7)->nullable()->after('location_address');
            $table->decimal('location_lng', 10, 7)->nullable()->after('location_lat');
        });
    }
};
