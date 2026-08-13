<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102000_prune_profile_foreign_default_entry_types.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Der EntryTypeSeeder legte bis jetzt bei jedem Deploy alle fünf Default-Typen
 * für jede Org neu an — vom Admin gelöschte profilfremde Typen (z. B.
 * Pflegebesuch/Klimatechnik in einer IT-Org) kamen so immer wieder. Einmalige
 * Bereinigung: profilfremde Spezial-Typen ohne Verwendung löschen; der Seeder
 * fasst bestehende Orgs künftig nicht mehr an.
 */
return new class extends Migration {
    public function up(): void {
        // Snapshot der Profil-Kopplung zum Migrationszeitpunkt (bewusst nicht
        // aus den Profil-Dateien gelesen — Migrationen bleiben eigenständig).
        $specialByProfile = [
            'it' => ['it_ticket'],
            'pflege' => ['care_visit'],
            'shk' => ['hvac_job'],
            'anlagenwartung' => ['hvac_job'],
        ];
        $allSpecials = ['care_visit', 'it_ticket', 'hvac_job'];

        foreach (DB::table('organizations')->get(['id', 'settings']) as $org) {
            $settings = json_decode((string) ($org->settings ?? ''), true);
            $code = is_array($settings) ? ($settings['branch_profile_code'] ?? null) : null;
            $prune = array_values(array_diff($allSpecials, $specialByProfile[$code] ?? []));
            if ($prune === []) {
                continue;
            }

            $this->deleteUnused(
                DB::table('entry_types')
                    ->where('organization_id', $org->id)
                    ->whereIn('slug', $prune)
            );
        }

        // Altlast: org-lose Seeder-Zeilen (organization_id NULL) sind im
        // org-gescopten Admin unsichtbar und nur Ballast.
        $this->deleteUnused(
            DB::table('entry_types')
                ->whereNull('organization_id')
                ->whereIn('slug', array_merge(['general', 'service'], $allSpecials))
        );
    }

    public function down(): void {
        // Daten-Bereinigung — nicht umkehrbar.
    }

    /** Löscht nur Typen ohne Referenzen (Einträge, Wiederholungsregeln). */
    private function deleteUnused(Builder $query): void {
        $query
            ->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))
                ->from('diary_entries')
                ->whereColumn('diary_entries.entry_type_id', 'entry_types.id'))
            ->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))
                ->from('recurrence_rules')
                ->whereColumn('recurrence_rules.entry_type_id', 'entry_types.id'))
            ->delete();
    }
};
