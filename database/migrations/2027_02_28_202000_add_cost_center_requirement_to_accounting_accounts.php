<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_202000_add_cost_center_requirement_to_accounting_accounts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1000: Kostenstelle ist beim Festschreiben Pflicht für Buchungen auf diesem Konto. */
return new class extends Migration {
    public function up(): void {
        Schema::table('accounting_accounts', function (Blueprint $table): void {
            $table->boolean('is_cost_center_required')->default(false)->after('is_clearing');
        });
    }

    public function down(): void {
        Schema::table('accounting_accounts', function (Blueprint $table): void {
            $table->dropColumn('is_cost_center_required');
        });
    }
};
