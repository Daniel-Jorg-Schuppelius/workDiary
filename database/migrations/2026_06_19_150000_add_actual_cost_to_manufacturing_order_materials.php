<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_150000_add_actual_cost_to_manufacturing_order_materials.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kumulierte Ist-Materialkosten je Position (Feature 047/048, E7). Wird beim
 * Verbrauch aus dem aktiven Bewertungsverfahren erfasst – Grundlage echter
 * Nachkalkulation.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('manufacturing_order_materials', function (Blueprint $table): void {
            $table->decimal('actual_cost', 18, 4)->default(0)->after('cost_snapshot');
        });
    }

    public function down(): void {
        Schema::table('manufacturing_order_materials', function (Blueprint $table): void {
            $table->dropColumn('actual_cost');
        });
    }
};
