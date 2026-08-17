<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_103000_add_change_order_fields_to_boq_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 108, MVP-624: Nachträge tragen in GAEB eine Nachtragsnummer (`CONo`)
 * und einen eigenen Status (`COStatus`, acht Zustände). Das bisherige Flag
 * `is_addendum` konnte beides nicht abbilden — und wurde nie gesetzt, weil der
 * Parser auf `STLNo` prüfte, ein Element, das es im Schema nicht gibt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('boq_items', function (Blueprint $table): void {
            $table->string('change_order_no', 30)->nullable()->after('is_addendum');
            $table->string('change_order_status', 20)->nullable()->after('change_order_no');
            $table->index(['bill_of_quantity_id', 'change_order_no'], 'boqi_boq_cono_idx');
        });
    }

    public function down(): void {
        Schema::table('boq_items', function (Blueprint $table): void {
            $table->dropIndex('boqi_boq_cono_idx');
            $table->dropColumn(['change_order_no', 'change_order_status']);
        });
    }
};
