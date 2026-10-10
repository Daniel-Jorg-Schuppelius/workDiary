<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_16_100600_add_sales_assignment_to_accounting_vouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1128: manuelle Vertriebszuordnung gespiegelter Rechnungen im
 * allgemeinen Belegspiegel — dieselbe Rolle wie an `invoices` und
 * `lexoffice_vouchers`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('accounting_vouchers', function (Blueprint $t): void {
            $t->foreignId('sales_user_id')->nullable()->after('supplier_id')->constrained('users')->nullOnDelete();
            $t->foreignId('sales_agent_id')->nullable()->after('sales_user_id')->constrained('commission_agents')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('accounting_vouchers', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('sales_agent_id');
            $t->dropConstrainedForeignId('sales_user_id');
        });
    }
};
