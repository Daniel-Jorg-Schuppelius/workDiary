<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_200000_add_billing_mode_to_customers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fakturierungsweg-Override je Kunde (Feature 045, „Führendes System").
 * NULL = Org-Default aus organizations.settings['billing_mode'] erben
 * (Fallback: workdiary). Aufgelöst über App\Services\Finance\BillingModeResolver.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('billing_mode', 16)->nullable()->after('billable');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('billing_mode');
        });
    }
};
