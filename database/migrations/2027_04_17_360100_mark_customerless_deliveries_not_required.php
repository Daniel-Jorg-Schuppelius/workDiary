<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_360100_mark_customerless_deliveries_not_required.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Eine Auslieferung ohne Kunden hat niemanden zu berechnen (MVP-1620). Neue
 * stehen sofort auf „nicht erforderlich“; offene Altfälle ziehen hier nach,
 * sonst blieben sie für immer offen.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('stock_deliveries')) {
            return;
        }

        DB::table('stock_deliveries')
            ->whereNull('customer_id')
            ->where('facturation_status', 'pending')
            ->update(['facturation_status' => 'not_required']);
    }

    public function down(): void {
        // Der alte Stand war ein Fehlzustand.
    }
};
