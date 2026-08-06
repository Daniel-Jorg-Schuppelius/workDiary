<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_01_100000_add_two_way_to_msgraph_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwei-Wege-Kalender (Feature 102, C3 — das in Bauturbo A8 verschobene Epic,
 * erster Schnitt): Opt-in `two_way` je Verbindung; der Rückimport läuft als
 * calendarView-DELTA (Checkpoint `calendar_delta_link`) und erzeugt NUR
 * Integrations-Inbox-Fälle (Termin-Vorschläge, extern-geändert-Konflikte,
 * Lösch-Hinweise) — nie blinde Event-Anlage (Leitsatz Feature 080/056).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('msgraph_connections', function (Blueprint $table): void {
            $table->boolean('two_way')->default(false)->after('teams_meetings');
            $table->text('calendar_delta_link')->nullable()->after('two_way'); // absolute Graph-URL
            $table->timestamp('last_imported_at')->nullable()->after('calendar_delta_link');
        });
    }

    public function down(): void {
        Schema::table('msgraph_connections', function (Blueprint $table): void {
            $table->dropColumn(['two_way', 'calendar_delta_link', 'last_imported_at']);
        });
    }
};
