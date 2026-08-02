<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_13_100000_fix_plugin_fqcn_ids.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Boot-Fehler wurden bis W0a unter dem FQCN statt der Plugin-ID aufgezeichnet
 * (Plugin-System-Review 2026-08, A1). Solche Waisen-Zeilen in plugin_errors/
 * plugin_states auf die Plugin-ID umschreiben; nicht mehr auflösbare Klassen
 * werden entfernt.
 */
return new class extends Migration {
    public function up(): void {
        foreach (['plugin_errors', 'plugin_states'] as $table) {
            $ids = DB::table($table)->distinct()->pluck('plugin_id');

            foreach ($ids as $fqcn) {
                if (! is_string($fqcn) || ! str_contains($fqcn, '\\')) {
                    continue;
                }

                $target = $this->pluginIdFor($fqcn);
                if ($target === null) {
                    DB::table($table)->where('plugin_id', $fqcn)->delete();

                    continue;
                }

                if ($table === 'plugin_states') {
                    // Unique (plugin_id, organization_id): existiert die Ziel-
                    // Zeile bereits, ist die FQCN-Zeile ein veralteter Waise.
                    foreach (DB::table($table)->where('plugin_id', $fqcn)->get() as $row) {
                        $exists = DB::table($table)
                            ->where('plugin_id', $target)
                            ->where('organization_id', $row->organization_id)
                            ->exists();
                        $exists
                            ? DB::table($table)->where('id', $row->id)->delete()
                            : DB::table($table)->where('id', $row->id)->update(['plugin_id' => $target]);
                    }

                    continue;
                }

                DB::table($table)->where('plugin_id', $fqcn)->update(['plugin_id' => $target]);
            }
        }
    }

    public function down(): void {
        // Datenbereinigung — nicht umkehrbar.
    }

    private function pluginIdFor(string $class): ?string {
        if (! class_exists($class) || ! defined("{$class}::ID")) {
            return null;
        }
        $id = $class::ID;

        return is_string($id) && $id !== '' ? $id : null;
    }
};
