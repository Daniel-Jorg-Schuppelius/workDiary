<?php
/*
 * Created on   : Thu Oct 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_210000_add_invoice_number_to_domain_accounting_entries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Live-Vertrag 2026-10-29 (domain:contract): das Buchungsjournal trägt je Buchung
// „accounting invoice id“ und „accounting reference“ — die Rechnung des Anbieters zur Buchung (Kennung, kein Fremdschlüssel).
return new class extends Migration {
    public function up(): void {
        Schema::table('domain_accounting_entries', function (Blueprint $t): void {
            $t->string('invoice_number', 64)->nullable()->after('invoice_period');
        });
    }

    public function down(): void {
        Schema::table('domain_accounting_entries', function (Blueprint $t): void {
            $t->dropColumn('invoice_number');
        });
    }
};
