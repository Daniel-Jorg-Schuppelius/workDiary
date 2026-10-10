<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_16_100000_split_lexoffice_voucher_sides.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Plugins\Lexoffice\Services\VoucherTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-1114 (E5): Der Belegsync stempelte bei Kontakten mit Doppelrolle Kunde
 * UND Lieferant an jeden Beleg. Kunde bleibt nur an Verkaufs-, Lieferant nur
 * an Einkaufsbelegen; der nächste Sync schreibt es ebenso.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('lexoffice_vouchers')) {
            return;
        }

        DB::table('lexoffice_vouchers')
            ->whereIn('voucher_type', VoucherTypes::EXPENSES)
            ->whereNotNull('customer_id')
            ->update(['customer_id' => null]);

        DB::table('lexoffice_vouchers')
            ->where(static fn ($q) => $q->whereNull('voucher_type')->orWhereNotIn('voucher_type', VoucherTypes::EXPENSES))
            ->whereNotNull('supplier_id')
            ->update(['supplier_id' => null]);
    }

    public function down(): void {
        // Nicht umkehrbar; der Kontaktlauf des Belegsyncs ist die Quelle.
    }
};
