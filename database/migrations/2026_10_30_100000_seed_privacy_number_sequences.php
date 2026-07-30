<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_30_100000_seed_privacy_number_sequences.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vollreview W1.1: Die Privacy-Nummern (DSV-/DSR-<Jahr>-NNNN) laufen jetzt über
 * den zentralen NumberSequenceService. Damit neue Nummern nicht mit den früher
 * count-basiert vergebenen Bestandsnummern kollidieren, wird je Organisation
 * und Jahr das höchste vergebene Suffix als Sequenz-Startwert übernommen.
 */
return new class extends Migration {
    public function up(): void {
        $sources = [
            ['table' => 'privacy_incidents', 'column' => 'incident_number', 'scope' => 'privacy_incident'],
            ['table' => 'privacy_data_subject_requests', 'column' => 'request_number', 'scope' => 'data_subject_request'],
        ];

        $now = now();

        foreach ($sources as $source) {
            $rows = DB::table($source['table'])
                ->whereNotNull($source['column'])
                ->get(['organization_id', $source['column'] . ' as number']);

            // Höchstes Suffix je Organisation und Nummern-Jahr (aus der Nummer
            // selbst, nicht aus Datumsfeldern — die Nummer ist die Wahrheit).
            $maxByOrgYear = [];
            foreach ($rows as $row) {
                if (! preg_match('/^[A-Z]+-(\d{4})-(\d+)$/', (string) $row->number, $m)) {
                    continue;
                }
                $key = $row->organization_id . '|' . $m[1];
                $maxByOrgYear[$key] = max($maxByOrgYear[$key] ?? 0, (int) $m[2]);
            }

            foreach ($maxByOrgYear as $key => $maxValue) {
                [$orgId, $year] = explode('|', $key);

                $exists = DB::table('number_sequences')
                    ->where('organization_id', (int) $orgId)
                    ->where('scope', $source['scope'])
                    ->where('period', $year)
                    ->first();

                if ($exists === null) {
                    DB::table('number_sequences')->insert([
                        'organization_id' => (int) $orgId,
                        'scope' => $source['scope'],
                        'period' => $year,
                        'last_value' => $maxValue,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } elseif ((int) $exists->last_value < $maxValue) {
                    DB::table('number_sequences')
                        ->where('id', $exists->id)
                        ->update(['last_value' => $maxValue, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void {
        // Seed-Migration: nichts zurückzurollen (Sequenzen bleiben gültig).
    }
};
