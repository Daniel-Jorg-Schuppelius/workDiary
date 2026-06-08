<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_08_120000_widen_address_zip_city_columns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLZ/Ort werden jetzt at-rest verschlüsselt (ContactAddress::$casts). Verschlüsselte
 * Werte sind deutlich länger als der Klartext, daher die bisher engen Spalten
 * (zip VARCHAR(32), city VARCHAR(128)) auf TEXT erweitern. Bestandsdaten werden
 * separat per `php artisan security:encrypt-existing` verschlüsselt.
 */
return new class extends Migration {
    public function up(): void {
        // SQLite erzwingt keine VARCHAR-Länge (speichert beliebig langen Text) –
        // Widen unnötig, und der ->change()-Table-Rebuild ist dort fehlerhaft.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }
        Schema::table('contact_addresses', function (Blueprint $table): void {
            $table->text('zip')->nullable()->change();
            $table->text('city')->nullable()->change();
        });
    }

    public function down(): void {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }
        Schema::table('contact_addresses', function (Blueprint $table): void {
            $table->string('zip', 32)->nullable()->change();
            $table->string('city', 128)->nullable()->change();
        });
    }
};
