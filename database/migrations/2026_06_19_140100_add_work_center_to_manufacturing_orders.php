<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_140100_add_work_center_to_manufacturing_orders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weist Fertigungsaufträge einem Arbeitsplatz mit geplanter Belegungsdauer zu
 * (Feature 047/048, E7).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('manufacturing_orders', function (Blueprint $table): void {
            $table->foreignId('work_center_id')->nullable()->after('warehouse_id')->constrained('work_centers')->nullOnDelete();
            $table->unsignedInteger('planned_minutes')->nullable()->after('work_center_id');
        });
    }

    public function down(): void {
        Schema::table('manufacturing_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_center_id');
            $table->dropColumn('planned_minutes');
        });
    }
};
