<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_19_100000_add_resolved_state_to_zammad_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 060, 2. Ausbaustufe: optionaler Status-Rückkanal. Ist `resolved_state`
 * gesetzt (z. B. `closed`), setzt eine in WorkDiary erledigte Aufgabe den
 * verknüpften Zammad-Ticketstatus entsprechend (schlanke Rückmeldung, keine
 * Vollsynchronisation). NULL = Rückkanal aus (Default) — Zammad bleibt führend.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('zammad_connections', function (Blueprint $table): void {
            $table->string('resolved_state', 64)->nullable()->after('queue_map');
        });
    }

    public function down(): void {
        Schema::table('zammad_connections', function (Blueprint $table): void {
            $table->dropColumn('resolved_state');
        });
    }
};
