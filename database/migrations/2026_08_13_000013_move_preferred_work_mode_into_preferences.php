<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000013_move_preferred_work_mode_into_preferences.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Konsolidiert die Arbeitsmodus-Präferenz: weg von der eigenen Spalte
 * users.preferred_work_mode, hinein in die bestehende Per-User-Bag
 * users.preferences['work_mode'] (analog theme/locale/startpage). Damit gibt es
 * nur EINEN Per-User-Präferenz-Mechanismus; die generische settings-Tabelle
 * bleibt für global/Org-Config reserviert.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('users', 'preferred_work_mode')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('preferred_work_mode')
            ->orderBy('id')
            ->each(function ($row): void {
                $prefs = json_decode((string) ($row->preferences ?? '{}'), true);
                if (! is_array($prefs)) {
                    $prefs = [];
                }
                $prefs['work_mode'] = $row->preferred_work_mode;
                DB::table('users')->where('id', $row->id)->update([
                    'preferences' => json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('preferred_work_mode');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('preferred_work_mode', 16)->nullable()->after('is_new_system');
        });
        // Werte verbleiben in preferences['work_mode'] – kein Rückschreiben.
    }
};
