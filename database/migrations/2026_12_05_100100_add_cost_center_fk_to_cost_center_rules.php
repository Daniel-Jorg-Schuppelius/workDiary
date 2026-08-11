<?php
/*
 * Created on   : Tue Aug 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_05_100100_add_cost_center_fk_to_cost_center_rules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Kostenstellen-Regeln an die Stammdaten (Feature 069) anbinden — der im
 * CostCenter-Model angekündigte Backfill: je (Organisation, Code) wird ein
 * Stammsatz sichergestellt und verknüpft. Der String `cost_center` bleibt als
 * Code-Snapshot/Fallback erhalten (Regel funktioniert auch nach Löschung des
 * Stammsatzes weiter, nullOnDelete).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('cost_center_rules', function (Blueprint $table): void {
            $table->foreignId('cost_center_id')->nullable()->after('team_id')
                ->constrained('cost_centers', indexName: 'ccr_cc_fk')->nullOnDelete();
        });

        $pairs = DB::table('cost_center_rules')
            ->select('organization_id', 'cost_center')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $code = (string) $pair->cost_center;
            // cost_centers.code ist auf 30 Zeichen begrenzt (Regel-Feld: 32) —
            // überlange Codes bleiben reiner String-Fallback ohne Stammsatz.
            if ($code === '' || mb_strlen($code) > 30) {
                continue;
            }

            $costCenterId = DB::table('cost_centers')
                ->where('organization_id', $pair->organization_id)
                ->where('code', $code)
                ->value('id');

            if ($costCenterId === null) {
                $costCenterId = DB::table('cost_centers')->insertGetId([
                    'organization_id' => $pair->organization_id,
                    'code' => $code,
                    'label' => $code,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('cost_center_rules')
                ->where('organization_id', $pair->organization_id)
                ->where('cost_center', $code)
                ->update(['cost_center_id' => $costCenterId]);
        }
    }

    public function down(): void {
        Schema::table('cost_center_rules', function (Blueprint $table): void {
            // SQLite kann FK-Constraints nicht einzeln entfernen; dropColumn genügt dort.
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('ccr_cc_fk');
            }
            $table->dropColumn('cost_center_id');
        });
    }
};
