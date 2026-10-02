<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_26_100000_add_scopes_to_caldav_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Publish-Scopes je CalDAV-Anbindung (Feature 058, Rang 17): welche
 * Kalender-Quellen die Verbindung publiziert — `events` (Termine) und/oder
 * `schedule` (Dienstpläne/Urlaube). Null/leer wird als „nur events" behandelt
 * (rückwärtskompatibel für Bestandsverbindungen).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('caldav_connections', function (Blueprint $table): void {
            $table->json('scopes')->nullable()->after('calendar_path');
        });
    }

    public function down(): void {
        Schema::table('caldav_connections', function (Blueprint $table): void {
            $table->dropColumn('scopes');
        });
    }
};
