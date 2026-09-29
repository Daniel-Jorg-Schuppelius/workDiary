<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_211100_close_finished_open_attendances.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MVP-1015: Importierte Anwesenheiten mit Endzeit blieben „open“ und fielen
 * aus Auswertungen und Prüfungen heraus. Abgeschlossen ist, was eine Endzeit hat.
 */
return new class extends Migration {
    public function up(): void {
        DB::table('attendances')->where('status', 'open')->whereNotNull('ended_at')->update(['status' => 'closed']);
    }

    public function down(): void {
        // Keine Rücknahme: welcher Satz vorher „open“ war, ist nicht mehr unterscheidbar.
    }
};
