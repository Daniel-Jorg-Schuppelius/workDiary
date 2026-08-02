<?php
/*
 * Created on   : Sat Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_18_100000_add_statement_link_to_account_payments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 098: Zahlungen zu einem Beleg zählen in den Monat, an dem der Beleg
 * hängt — nicht in den ihres Zahldatums. Retainer-Rechnungen gehen am
 * Monatsende raus und werden Anfang des Folgemonats bezahlt; ohne diese
 * Zuordnung stünde jede Monatszeile mit der Zahlung des Vormonats da.
 * `paid_on` bleibt das echte Zahldatum (Nachweis), NULL = Zuordnung wie bisher
 * über das Datum (Bank-/Hand-/Import-Zahlungen).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customer_account_payments', function (Blueprint $table): void {
            $table->foreignId('customer_billing_statement_id')->nullable()->after('customer_billing_agreement_id')
                ->constrained('customer_billing_statements', indexName: 'fk_cap_statement')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('customer_account_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_billing_statement_id');
        });
    }
};
