<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_13_100100_unify_caldav_reference_type.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CalDAV-Referenztyp (MVP-1094): Publish schrieb `calendar_object`, der
 * Rückimport sucht `calendar_event`. Eigene Termine kamen deshalb als
 * Vorschläge zurück, Änderungen und Löschungen blieben unerkannt. Bestehende
 * Referenzen ziehen um — ohne sie würde der nächste Publish jeden Termin
 * ein zweites Mal anlegen —, offene Vorschläge zu eigenen Terminen entfallen.
 */
return new class extends Migration {
    public function up(): void {
        DB::table('external_references')
            ->where('plugin_id', 'caldav')
            ->where('external_type', 'calendar_object')
            ->orderBy('id')
            ->chunkById(500, function ($references): void {
                foreach ($references as $reference) {
                    $taken = DB::table('external_references')
                        ->where('plugin_id', 'caldav')
                        ->where('external_type', 'calendar_event')
                        ->where('referenceable_type', $reference->referenceable_type)
                        ->where('referenceable_id', $reference->referenceable_id)
                        ->exists();
                    if ($taken) {
                        continue;
                    }

                    DB::table('external_references')->where('id', $reference->id)->update(['external_type' => 'calendar_event']);

                    $uid = json_decode((string) $reference->payload, true)['uid'] ?? null;
                    DB::table('integration_inbox_items')
                        ->where('organization_id', $reference->organization_id)
                        ->where('plugin_id', 'caldav')
                        ->where('status', 'open')
                        ->where(function ($query) use ($reference, $uid): void {
                            $query->where('dedupe_key', 'calendar-proposal:' . $reference->external_id);
                            if (is_string($uid) && $uid !== '') {
                                $query->orWhere(fn ($series) => $series->whereLikeEscaped('dedupe_key', 'calendar-proposal:' . $uid . ':', 'prefix'));
                            }
                        })
                        ->update(['status' => 'dismissed', 'resolved_at' => now()]);
                }
            });
    }

    public function down(): void {
        // Rückweg wäre der Fehler selbst — die Umstellung bleibt bestehen.
    }
};
