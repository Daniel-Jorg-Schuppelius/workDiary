<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_160000_add_asset_decommission_and_return_columns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-1574: Grund der Außerbetriebnahme und Notiz zur Rücknahme. Der wählbare
 * Status „gesperrt“/„in Reparatur“ erzeugt ab jetzt eine Sperre in
 * `asset_blocks` (Quelle: das Asset selbst); der Bestand wird nachgezogen,
 * damit die Einsatzprüfung nur ein Sperrmodell liest.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('decommission_reason', 500)->nullable()->after('decommissioned_on');
        });

        Schema::table('asset_assignments', function (Blueprint $table): void {
            $table->string('return_note', 2000)->nullable()->after('condition_in');
        });

        $now = now();
        DB::table('assets')
            ->whereIn('status', ['blocked', 'inRepair'])
            ->orderBy('id')
            ->chunkById(500, function ($assets) use ($now): void {
                foreach ($assets as $asset) {
                    $hasStatusBlock = DB::table('asset_blocks')
                        ->where('asset_id', $asset->id)
                        ->where('source_type', 'assets')
                        ->where('source_id', $asset->id)
                        ->whereNull('released_at')
                        ->exists();
                    // Status „gesperrt“ aus einem blockierenden Defekt trägt der Defekt selbst.
                    $hasBlockingDefect = $asset->status === 'blocked' && DB::table('asset_defects')
                        ->where('asset_id', $asset->id)
                        ->where('blocks_usage', true)
                        ->whereIn('status', ['open', 'inRepair'])
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($hasStatusBlock || $hasBlockingDefect) {
                        continue;
                    }

                    DB::table('asset_blocks')->insert([
                        'organization_id' => $asset->organization_id,
                        'asset_id' => $asset->id,
                        'reason' => $asset->status === 'inRepair' ? 'maintenance' : 'manual',
                        'source_type' => 'assets',
                        'source_id' => $asset->id,
                        'blocked_from' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void {
        Schema::table('asset_assignments', function (Blueprint $table): void {
            $table->dropColumn('return_note');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('decommission_reason');
        });
    }
};
