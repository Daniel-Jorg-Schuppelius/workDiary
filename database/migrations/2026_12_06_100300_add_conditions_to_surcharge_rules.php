<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_100300_add_conditions_to_surcharge_rules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-513 P1 (Feature 103): kombinierbare Bedingungen an Zuschlagsregeln —
 * JSON `{team_ids: [], site_ids: [], shift_type_ids: []}`, NULL/leer =
 * gilt für alle (Bestandsverhalten bleibt unverändert).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('surcharge_rules', function (Blueprint $table): void {
            $table->json('conditions')->nullable()->after('valid_until');
        });
    }

    public function down(): void {
        Schema::table('surcharge_rules', function (Blueprint $table): void {
            $table->dropColumn('conditions');
        });
    }
};
