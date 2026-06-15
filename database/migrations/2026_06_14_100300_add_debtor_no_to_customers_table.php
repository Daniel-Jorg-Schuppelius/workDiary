<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_100300_add_debtor_no_to_customers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Debitorennummer je Kunde (Feature 045, „Priorität 2": Debitoren-/
 * Kreditorennummern oder eine dokumentierte Vergaberegel). Nullable und additiv:
 * fehlt eine explizite Nummer, leitet der DatevBookingService sie deterministisch
 * aus der konfigurierten Nummernkreis-Basis + Kunden-Offset ab (siehe
 * DatevBookingConfig::debtorAccountFor()).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('debtor_no', 12)->nullable()->after('buyer_reference');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('debtor_no');
        });
    }
};
