<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_10_100200_backfill_resolved_at_on_closed_inbox_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Altbestand der Zuordnungs-Inbox: verworfene FRITZ!Box-Anrufe wurden ohne
 * Erledigt-Datum geschlossen und fielen nie unter den Aufräumlauf
 * (`integration:purge-inbox` verlangt `resolved_at`). Die Frist beginnt mit
 * der Migration, nicht rückwirkend: ein verworfener Fall sperrt auch das
 * erneute Vormerken desselben Anrufs — rückdatiert löschte der erste Lauf
 * nach dem Deploy den ganzen Altbestand, und Anrufe, die noch in der
 * Anrufliste der Box stehen, tauchten wieder offen auf. Idempotent.
 */
return new class extends Migration {
    public function up(): void {
        // Query-Builder: `updated_at` der Zeilen bleibt unberührt.
        DB::table('integration_inbox_items')
            ->where('status', '!=', 'open')
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    public function down(): void {
        // Datenbereinigung — kein Rückweg.
    }
};
