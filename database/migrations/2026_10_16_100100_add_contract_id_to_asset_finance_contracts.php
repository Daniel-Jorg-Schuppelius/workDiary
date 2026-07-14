<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_16_100100_add_contract_id_to_asset_finance_contracts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welle D — additive Verknüpfung: ein Leasing-/Finanzierungsvertrag
 * (Feature 074) kann optional auf einen allgemeinen Vertrag (CLM) zeigen.
 * Der Spezialfall bleibt voll funktionsfähig; Bestandsdaten werden NICHT
 * migriert — nur die Verknüpfungsmöglichkeit geschaffen (nullable, nullOnDelete).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('asset_finance_contracts', function (Blueprint $table): void {
            $table->foreignId('contract_id')->nullable()->after('organization_id')
                ->constrained('contracts', indexName: 'af_contracts_contract_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('asset_finance_contracts', function (Blueprint $table): void {
            $table->dropForeign('af_contracts_contract_fk');
            $table->dropColumn('contract_id');
        });
    }
};
