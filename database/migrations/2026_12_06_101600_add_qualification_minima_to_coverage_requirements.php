<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_101600_add_qualification_minima_to_coverage_requirements.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-530: Qualifikations-Mindestbesetzung („mindestens 2 Examinierte in der
 * Frühschicht") — Map qualification_id → Mindestanzahl je Bedarfsregel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('coverage_requirements', function (Blueprint $table): void {
            $table->json('qualification_minima')->nullable()->after('required_qualification_ids');
        });
    }

    public function down(): void {
        Schema::table('coverage_requirements', function (Blueprint $table): void {
            $table->dropColumn('qualification_minima');
        });
    }
};
