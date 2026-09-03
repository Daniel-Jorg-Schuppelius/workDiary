<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_111500_add_strict_products_to_reselling_reconciliation_runs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 151: Laufoption „Produkt streng prüfen" — Standard ist tolerant, weil
 * Sammelrechnungen die Edition selten im Positionstext nennen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('reselling_reconciliation_runs', function (Blueprint $t): void {
            $t->boolean('strict_products')->default(false)->after('window_after');
        });
    }

    public function down(): void {
        Schema::table('reselling_reconciliation_runs', function (Blueprint $t): void {
            $t->dropColumn('strict_products');
        });
    }
};
