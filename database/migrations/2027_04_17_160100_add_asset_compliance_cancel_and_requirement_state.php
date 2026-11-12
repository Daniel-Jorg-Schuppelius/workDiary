<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_160100_add_asset_compliance_cancel_and_requirement_state.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1575: Prüftermine lassen sich mit Grund stornieren; Anforderungen eines
 * Prüfprofils werden deaktiviert statt gelöscht, weil Ergebniszeilen alter
 * Prüfprotokolle auf sie verweisen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('asset_inspection_schedules', function (Blueprint $table): void {
            $table->string('cancel_reason', 500)->nullable()->after('note');
        });

        Schema::table('asset_compliance_requirements', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('is_mandatory');
        });
    }

    public function down(): void {
        Schema::table('asset_compliance_requirements', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });

        Schema::table('asset_inspection_schedules', function (Blueprint $table): void {
            $table->dropColumn('cancel_reason');
        });
    }
};
