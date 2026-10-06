<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_10_100600_backfill_fritzbox_dismissed_calls.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Plugins\Fritzbox\Models\FritzboxDismissedCall;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Altbestand: bisher verworfene FRITZ!Box-Fälle tragen noch keine Sperrmarke;
 * sobald `integration:purge-inbox` sie löscht, tauchten die Anrufe wieder
 * auf. Die Marke entsteht aus dem gespeicherten Anrufschlüssel (`external_id`,
 * ersatzweise `dedupe_key` ohne Typpräfix) mit demselben Verfahren wie zur
 * Laufzeit. Idempotent (Unique-Index, insertOrIgnore).
 */
return new class extends Migration {
    public function up(): void {
        DB::table('integration_inbox_items')
            ->where('plugin_id', 'fritzbox')
            ->where('external_type', 'call')
            ->where('status', 'dismissed')
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->chunk(500, function (Collection $rows): void {
                $marks = [];
                foreach ($rows as $row) {
                    $callKey = $row->external_id
                        ?? (str_starts_with((string) $row->dedupe_key, 'call:') ? substr((string) $row->dedupe_key, 5) : null);
                    if ($callKey === null || $callKey === '') {
                        continue;
                    }
                    $marks[] = [
                        'organization_id' => (int) $row->organization_id,
                        'call_hash' => FritzboxDismissedCall::hashFor((int) $row->organization_id, (string) $callKey),
                        'dismissed_at' => $row->resolved_at ?? now(),
                    ];
                }
                if ($marks !== []) {
                    DB::table('fritzbox_dismissed_calls')->insertOrIgnore($marks);
                }
            });
    }

    public function down(): void {
        // Datenbereinigung — kein Rückweg.
    }
};
