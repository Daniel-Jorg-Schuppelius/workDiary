<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_194000_add_reversal_kind_to_invoice_commissions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-989: Anlass der Rückrechnung, damit eine zurückgenommene Zahlung bei erneuter Zahlung wieder Provision auslöst. */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_commissions', function (Blueprint $table): void {
            $table->string('reversal_kind', 20)->nullable()->after('reversal_of_id');
        });
    }

    public function down(): void {
        Schema::table('invoice_commissions', function (Blueprint $table): void {
            $table->dropColumn('reversal_kind');
        });
    }
};
