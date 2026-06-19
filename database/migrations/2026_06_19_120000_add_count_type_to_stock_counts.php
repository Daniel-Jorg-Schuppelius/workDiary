<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_120000_add_count_type_to_stock_counts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unterscheidet Voll- und zyklische Inventur (Feature 048, E6).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('stock_counts', function (Blueprint $table): void {
            $table->string('count_type', 8)->default('full')->after('status'); // StockCountType
        });
    }

    public function down(): void {
        Schema::table('stock_counts', function (Blueprint $table): void {
            $table->dropColumn('count_type');
        });
    }
};
