<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_11_120000_add_bank_columns_to_customers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bankverbindung am Kunden hinterlegen (z. B. für SEPA-Hinweis auf Rechnungen).
 * Lexoffice-API exponiert diese Daten nicht — die Pflege erfolgt rein lokal.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('bank_account_holder', 200)->nullable()->after('invoice_text');
            $table->string('bank_iban', 64)->nullable()->after('bank_account_holder');
            $table->string('bank_bic', 32)->nullable()->after('bank_iban');
            $table->string('bank_name', 200)->nullable()->after('bank_bic');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['bank_account_holder', 'bank_iban', 'bank_bic', 'bank_name']);
        });
    }
};
