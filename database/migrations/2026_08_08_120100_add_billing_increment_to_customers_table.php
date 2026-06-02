<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_08_120100_add_billing_increment_to_customers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Default-Abrechnungs-Taktung am Kunden (Vererbungs-Wurzel für seine Projekte).
 * Siehe {@see 2026_08_08_120000_add_billing_increment_to_projects_table.php}.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('billing_increment_minutes')->nullable()->after('internal_rate');
            $table->unsignedSmallInteger('billing_grouping_gap_minutes')->nullable()->after('billing_increment_minutes');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['billing_increment_minutes', 'billing_grouping_gap_minutes']);
        });
    }
};
