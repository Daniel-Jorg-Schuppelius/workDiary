<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_260300_release_recorded_club_exam_results.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ergebnisfreigabe der Graduierungsprüfung (MVP-1616): Mitglieder sehen
 * Ergebnisse erst nach der Freigabe. Bereits erfasste Ergebnisse waren bisher
 * sofort sichtbar — diese Prüfungen gelten als freigegeben, damit niemand
 * einen schon angezeigten Grad wieder verliert.
 */
return new class extends Migration {
    public function up(): void {
        $offers = DB::table('club_exam_candidates')
            ->whereNotNull('result_recorded_at')
            ->groupBy('club_exam_offer_id')
            ->selectRaw('club_exam_offer_id, MAX(result_recorded_at) AS released_at')
            ->get();

        foreach ($offers as $offer) {
            DB::table('club_exam_offers')
                ->where('id', $offer->club_exam_offer_id)
                ->whereNull('results_released_at')
                ->update(['results_released_at' => $offer->released_at]);
        }
    }

    public function down(): void {
        // Die Freigabe ist ein fachlicher Stand und wird nicht zurückgenommen.
    }
};
