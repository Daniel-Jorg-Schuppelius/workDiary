<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_330400_lock_flex_balances_of_approved_months.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MVP-1627: `flex_balances.locked` friert den Saldo freigegebener Monate ein.
 * Bestand: Monate mit freigegebener bzw. gesperrter Monatsfreigabe werden
 * gesperrt, damit der Nachtlauf sie nicht mehr neu rechnet.
 */
return new class extends Migration {
    public function up(): void {
        DB::table('flex_balances')
            ->where('locked', false)
            ->whereExists(fn ($q) => $q->from('month_closures')
                ->whereColumn('month_closures.user_id', 'flex_balances.user_id')
                ->whereColumn('month_closures.period_year', 'flex_balances.year')
                ->whereColumn('month_closures.period_month', 'flex_balances.month')
                ->whereIn('month_closures.status', ['approved', 'locked']))
            ->update(['locked' => true]);
    }

    public function down(): void {
        // Vorher las niemand die Spalte; sie bleibt, wie sie ist.
    }
};
