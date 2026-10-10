<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_14_110200_add_is_collective_to_suppliers_and_customers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sammellieferant und Sammelkunde (Feature 163, MVP-1109): gekennzeichnete Stammsätze. */
return new class extends Migration {
    public function up(): void {
        foreach (['suppliers', 'customers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->boolean('is_collective')->default(false);
            });
        }
    }

    public function down(): void {
        foreach (['suppliers', 'customers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('is_collective');
            });
        }
    }
};
