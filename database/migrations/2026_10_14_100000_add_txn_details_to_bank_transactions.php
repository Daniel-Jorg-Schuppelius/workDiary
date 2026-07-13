<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_14_100000_add_txn_details_to_bank_transactions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toolkit-Folgepaket 2 (Feature 045, Auto-Split zu MVP-334):
 *
 *  - bank_transactions.transaction_details: Einzeltransaktionen (TxDtls) einer
 *    Sammelbuchung als JSON-Liste (Betrag signiert, EndToEndId, Mandat,
 *    Gegenpartei-Name/-IBAN + Hash, Zweck, Rückgabegrund je Detail). NUR bei
 *    Buchungen mit mehreren TxDtls gefüllt — die Bank-Buchung selbst bleibt
 *    EINE Zeile (Kontoauszugs-Treue). Enthält PII (Namen/IBAN/Zweck) und liegt
 *    daher wie counterparty_name/purpose VERSCHLÜSSELT at-rest
 *    (`encrypted:array`-Cast ⇒ text-Spalte, kein natives JSON; mediumText,
 *    weil SEPA-Sammler hunderte Details tragen können).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->mediumText('transaction_details')->nullable()->after('return_reason');
        });
    }

    public function down(): void {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropColumn('transaction_details');
        });
    }
};
