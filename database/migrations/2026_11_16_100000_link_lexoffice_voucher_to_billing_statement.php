<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_16_100000_link_lexoffice_voucher_to_billing_statement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 098 (Retainer-Modus): verknüpft einen Monat mit einer Pauschal-
 * rechnung, die DIREKT in Lexoffice erstellt wurde. retainer_invoice_id deckt
 * nur den umgekehrten Weg ab (workDiary pusht den Beleg); wer die Pauschale
 * schon in Lexoffice führt, hatte bisher keinen Anker für den Zahlungs-Rücksync.
 * Unique: ein Beleg gehört zu höchstens einem Monat.
 *
 * Dazu `lexoffice_vouchers.net_amount`: die voucherlist liefert nur Brutto,
 * der Leistungssaldo rechnet aber netto. Der Nettobetrag wird bei Bedarf per
 * Beleg-Detailabruf nachgeladen und hier gecacht (ein Call je Beleg, nicht je Lauf).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customer_billing_statements', function (Blueprint $table): void {
            $table->foreignId('lexoffice_voucher_id')->nullable()
                ->constrained('lexoffice_vouchers', indexName: 'fk_cbs_lexoffice_voucher')->nullOnDelete();
            $table->unique('lexoffice_voucher_id', 'uq_cbs_lexoffice_voucher');
        });

        Schema::table('lexoffice_vouchers', function (Blueprint $table): void {
            $table->decimal('net_amount', 15, 2)->nullable()->after('open_amount');
        });
    }

    public function down(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $table): void {
            $table->dropColumn('net_amount');
        });

        Schema::table('customer_billing_statements', function (Blueprint $table): void {
            $table->dropUnique('uq_cbs_lexoffice_voucher');
            $table->dropConstrainedForeignId('lexoffice_voucher_id');
        });
    }
};
