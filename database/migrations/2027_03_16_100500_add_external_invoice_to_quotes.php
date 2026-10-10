<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_16_100500_add_external_invoice_to_quotes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1124 (E18): Angebot von Hand als „im Buchhaltungsprogramm abgerechnet“
 * markieren — zwischen lokalem Angebot und dort geschriebener Rechnung gibt es
 * keine Verbindung, die sich auswerten ließe.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('quotes', function (Blueprint $t): void {
            $t->timestamp('invoiced_externally_at')->nullable()->after('decided_at');
            $t->string('external_invoice_number', 64)->nullable()->after('invoiced_externally_at');
        });
    }

    public function down(): void {
        Schema::table('quotes', function (Blueprint $t): void {
            $t->dropColumn(['invoiced_externally_at', 'external_invoice_number']);
        });
    }
};
