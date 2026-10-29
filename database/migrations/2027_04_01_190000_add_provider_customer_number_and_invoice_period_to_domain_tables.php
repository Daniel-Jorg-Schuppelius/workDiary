<?php
/*
 * Created on   : Thu Oct 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_190000_add_provider_customer_number_and_invoice_period_to_domain_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Befund 2026-10-29 (Feature 083/152): Das Portal stellt Subresellern Monatsrechnungen
// „<Kundennummer>-MM-JJJJ“; die Kundennummer kennt nur der Inhaber. Das Buchungsjournal
// trägt je Buchung den Gültigkeitszeitraum (INVOICE_PERIOD) — die Laufzeit einer Verlängerung.
return new class extends Migration {
    public function up(): void {
        Schema::table('domain_reseller_accounts', function (Blueprint $t): void {
            $t->string('provider_customer_number', 32)->nullable()->after('customer_id');
        });
        Schema::table('domain_accounting_entries', function (Blueprint $t): void {
            $t->string('invoice_period', 64)->nullable()->after('description');
        });
    }

    public function down(): void {
        Schema::table('domain_accounting_entries', function (Blueprint $t): void {
            $t->dropColumn('invoice_period');
        });
        Schema::table('domain_reseller_accounts', function (Blueprint $t): void {
            $t->dropColumn('provider_customer_number');
        });
    }
};
