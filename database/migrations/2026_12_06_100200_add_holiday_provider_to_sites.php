<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_100200_add_holiday_provider_to_sites.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-513 P0 (Feature 103): Feiertags-Rechtsraum je Standort — Yasumi-
 * Provider-Pfad (App\Support\HolidayRegions), NULL = Org-Einstellung
 * (`holidays.provider`). Grundlage für „Feiertag am Einsatzort" in der
 * Zeitregel-Bewertung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('sites', function (Blueprint $table): void {
            $table->string('holiday_provider', 120)->nullable()->after('country');
        });
    }

    public function down(): void {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn('holiday_provider');
        });
    }
};
