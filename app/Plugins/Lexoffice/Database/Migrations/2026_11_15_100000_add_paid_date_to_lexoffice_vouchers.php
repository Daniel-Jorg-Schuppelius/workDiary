<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_15_100000_add_paid_date_to_lexoffice_vouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zahlungsdatum am Lexoffice-Beleg-Spiegel (Phase-54-Nachtrag): Die
 * voucherlist liefert kein paidDate — es wird je bezahltem Beleg über den
 * Payments-Endpunkt nachgeladen ({@see \App\Plugins\Lexoffice\LexofficeVoucherSync::enrichPaidDates()})
 * und macht den Zahlungsverhaltens-Report auch bei externer
 * Rechnungshoheit aussagekräftig (Zahldauer/DSO-Historie).
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('lexoffice_vouchers', 'paid_date')) {
            return; // idempotent — Spalte kann aus einem früheren Lauf stammen
        }

        Schema::table('lexoffice_vouchers', function (Blueprint $table): void {
            $table->date('paid_date')->nullable()->after('due_date');
        });
    }

    public function down(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $table): void {
            $table->dropColumn('paid_date');
        });
    }
};
