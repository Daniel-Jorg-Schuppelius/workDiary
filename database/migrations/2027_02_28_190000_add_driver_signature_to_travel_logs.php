<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_190000_add_driver_signature_to_travel_logs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-992: Fahrer-Signatur einer Fahrtenbuch-Fahrt (Bild, Zeitpunkt, Prüfwert). */
return new class extends Migration {
    public function up(): void {
        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->timestamp('driver_signed_at')->nullable()->after('locked_at');
            $table->string('driver_signature_path', 255)->nullable()->after('driver_signed_at');
            $table->string('driver_signature_hash', 128)->nullable()->after('driver_signature_path');
        });
    }

    public function down(): void {
        Schema::table('travel_logs', function (Blueprint $table): void {
            $table->dropColumn(['driver_signed_at', 'driver_signature_path', 'driver_signature_hash']);
        });
    }
};
