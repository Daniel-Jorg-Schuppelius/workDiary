<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_210000_add_wait_until_to_procedure_step_runs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serverseitige Warte-/Trockenschritte (Feature 047, MVP-064): `wait_until` ist
 * der früheste Fortsetzungszeitpunkt. Die Blockade wird gegen diesen
 * persistierten Zeitpunkt geprüft – Neuladen oder ein anderer Client kann sie
 * nicht umgehen. Vorzeitige Fortsetzung ist nur als auditierte Abweichung
 * möglich.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('procedure_step_runs', function (Blueprint $table): void {
            $table->timestamp('wait_started_at')->nullable()->after('executed_at');
            $table->timestamp('wait_until')->nullable()->after('wait_started_at');
        });
    }

    public function down(): void {
        Schema::table('procedure_step_runs', function (Blueprint $table): void {
            $table->dropColumn(['wait_started_at', 'wait_until']);
        });
    }
};
