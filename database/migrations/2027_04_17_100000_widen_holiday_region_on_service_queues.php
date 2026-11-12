<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_100000_widen_holiday_region_on_service_queues.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1548: Die Feiertagsregion der Queue nimmt den Rechtsraum auf, den auch
 * Organisation und Standort führen (etwa `Germany\NorthRhineWestphalia`);
 * zehn Zeichen reichten dafür nicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('service_queues', function (Blueprint $table): void {
            $table->string('holiday_region', 80)->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('service_queues', function (Blueprint $table): void {
            $table->string('holiday_region', 10)->nullable()->change();
        });
    }
};
