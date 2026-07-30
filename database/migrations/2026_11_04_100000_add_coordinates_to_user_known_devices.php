<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_04_100000_add_coordinates_to_user_known_devices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Impossible-Travel-Erkennung (Feature 097, MVP-449): grobe Koordinaten der
 * letzten Anmeldung je bekanntem Gerät. Datensparsam — Stadt-Ebene aus der
 * lokalen `.mmdb`, keine IP im Klartext, kein Verlauf.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('user_known_devices', function (Blueprint $table): void {
            $table->decimal('latitude', 9, 6)->nullable()->after('country');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
        });
    }

    public function down(): void {
        Schema::table('user_known_devices', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
