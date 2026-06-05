<?php
/*
 * Created on   : Fri Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_11_120200_add_last_ok_at_to_plugin_states.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Zuletzt erreichbar" je Plugin/Organisation: getrennt vom letzten
 * Check-Zeitpunkt (last_health_check_at), damit die UI „seit X nicht mehr
 * erreichbar" anzeigen kann.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('plugin_states', function (Blueprint $table): void {
            $table->timestamp('last_ok_at')->nullable()->after('last_health_message');
        });
    }

    public function down(): void {
        Schema::table('plugin_states', function (Blueprint $table): void {
            $table->dropColumn('last_ok_at');
        });
    }
};
