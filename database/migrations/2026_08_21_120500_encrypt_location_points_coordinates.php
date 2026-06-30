<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_21_120500_encrypt_location_points_coordinates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verschlüsselt die persönliche Bewegungsspur at-rest: lat/lng werden von
 * decimal auf text umgestellt, damit der `encrypted`-Cast (siehe LocationPoint)
 * greift. Es gibt noch keine Produktivdaten, daher ist keine Datenmigration
 * nötig. Nach lat/lng wird nicht numerisch abgefragt (Geofence-Matching läuft
 * in PHP), die Spalten bleiben also nur über recorded_at/user indiziert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('location_points', function (Blueprint $table): void {
            $table->text('lat')->change();
            $table->text('lng')->change();
        });
    }

    public function down(): void {
        Schema::table('location_points', function (Blueprint $table): void {
            $table->decimal('lat', 10, 7)->change();
            $table->decimal('lng', 10, 7)->change();
        });
    }
};
