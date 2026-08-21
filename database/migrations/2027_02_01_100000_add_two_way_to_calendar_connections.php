<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_01_100000_add_two_way_to_calendar_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kalender-Rückimport (Feature 121, MVP-610).
 *
 * `two_way` ist Opt-in je Verbindung — der Rückimport ändert Daten und darf
 * nicht ungefragt anlaufen (Muster Msgraph, Feature 102/C3). Der Checkpoint
 * heißt bei Google `sync_token`, bei CalDAV ebenfalls (RFC 6578); wo der
 * Server keinen Sync-Report kann, bleibt er leer und der Abgleich läuft über
 * das Zeitfenster.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('google_calendar_connections', function (Blueprint $table): void {
            $table->boolean('two_way')->default(false)->after('calendar_name');
            $table->string('sync_token', 512)->nullable()->after('two_way');
            $table->timestamp('last_imported_at')->nullable()->after('sync_token');
        });

        Schema::table('caldav_connections', function (Blueprint $table): void {
            $table->boolean('two_way')->default(false)->after('calendar_path');
            $table->string('sync_token', 512)->nullable()->after('two_way');
            $table->timestamp('last_imported_at')->nullable()->after('sync_token');
        });
    }

    public function down(): void {
        Schema::table('google_calendar_connections', function (Blueprint $table): void {
            $table->dropColumn(['two_way', 'sync_token', 'last_imported_at']);
        });
        Schema::table('caldav_connections', function (Blueprint $table): void {
            $table->dropColumn(['two_way', 'sync_token', 'last_imported_at']);
        });
    }
};
