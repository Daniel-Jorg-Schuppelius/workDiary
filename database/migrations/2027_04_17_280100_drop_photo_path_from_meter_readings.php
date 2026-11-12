<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_280100_drop_photo_path_from_meter_readings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1619 (G-08): Das Foto eines Zählerstands ist ein Anhang. Die Spalte
 * nahm einen Pfad aus der Eingabe an, die App legte dort nie eine Datei ab.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('meter_readings', 'photo_path')) {
            return;
        }
        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
        });
    }

    public function down(): void {
        if (Schema::hasColumn('meter_readings', 'photo_path')) {
            return;
        }
        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->string('photo_path', 255)->nullable()->after('read_by_user_id');
        });
    }
};
