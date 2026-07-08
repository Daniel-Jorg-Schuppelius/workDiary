<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_07_110000_add_weather_auto_fetch_to_projects.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projekt-Override für den automatischen Wetter-Abruf (Feature 062, MVP-131,
 * Rang 12): nullable Boolean mit Vererbung wie `billable` — null = „erben"
 * (Org-Setting `weather.auto_fetch`), true/false = projektweit erzwingen.
 * Präzedenz Projekt > Org.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('projects', function (Blueprint $table): void {
            $table->boolean('weather_auto_fetch')->nullable()->after('billable');
        });
    }

    public function down(): void {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('weather_auto_fetch');
        });
    }
};
