<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_28_100000_add_teams_meetings_to_msgraph_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teams-Meeting-Link beim Kalender-Publish (MS365-Plan C1): Opt-in je
 * Verbindung — neu publizierte Termine bekommen `isOnlineMeeting` +
 * `joinUrl`. Nur beim Anlegen (Graph: irreversibel pro Event); Bestandstermine
 * bleiben unangetastet.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('msgraph_connections', function (Blueprint $table): void {
            $table->boolean('teams_meetings')->default(false)->after('calendar_name');
        });
    }

    public function down(): void {
        Schema::table('msgraph_connections', function (Blueprint $table): void {
            $table->dropColumn('teams_meetings');
        });
    }
};
