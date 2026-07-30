<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_06_100000_backfill_toggl_project_billable_to_inherit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Toggl-importierte Projekte trugen ein hartes billable=false: Export wie API
 * liefern das Feld für jedes Projekt (Free-Plan: immer false, das Flag ist dort
 * Premium), und der Importer schrieb es 1:1 in die Spalte. Solange der Wert
 * nirgends konsumiert wurde, war das folgenlos — seit dem Abrechenbar-Schalter
 * (effectiveBillable in RateCalculator + TimeEntry-Default) zöge er Umsatz und
 * neue Einträge fälschlich auf „nicht abrechenbar". Daher zurück auf null
 * (= erben von Parent/Kunde); bewusste Nein-Entscheidungen waren vor dem
 * Schalter im UI nicht möglich. Der Importer übernimmt künftig nur noch true.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('projects')) {
            return;
        }

        $type = (new Project)->getMorphClass();

        $ids = collect();
        foreach (['external_references', 'external_reference_aliases'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $ids = $ids->merge(
                DB::table($table)
                    ->where('plugin_id', 'toggl')
                    ->where('referenceable_type', $type)
                    ->pluck('referenceable_id')
            );
        }

        $ids->unique()->chunk(500)->each(function ($chunk): void {
            DB::table('projects')
                ->whereIn('id', $chunk->all())
                ->where('billable', false)
                ->update(['billable' => null]);
        });
    }
};
