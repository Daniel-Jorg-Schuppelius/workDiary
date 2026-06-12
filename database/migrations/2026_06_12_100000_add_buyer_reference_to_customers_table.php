<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_12_100000_add_buyer_reference_to_customers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Käuferreferenz/Leitweg-ID (BT-10) je Kunde für die E-Rechnung
 * (Feature 045, Abschnitt 8 — XRechnung). In der XRechnung ist die
 * BuyerReference Pflicht; ohne sie schlägt der E-Rechnungs-Preflight an.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('buyer_reference', 64)->nullable()->after('billing_mode');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('buyer_reference');
        });
    }
};
