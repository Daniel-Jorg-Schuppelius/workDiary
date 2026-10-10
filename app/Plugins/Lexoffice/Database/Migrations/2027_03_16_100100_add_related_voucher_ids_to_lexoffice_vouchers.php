<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_16_100100_add_related_voucher_ids_to_lexoffice_vouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1114 (E6): `relatedVouchers` des Detailabrufs — eine Gutschrift nennt
 * dort die Rechnung, die sie mindert (Provisionen, MVP-1119).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            $t->json('related_voucher_ids')->nullable()->after('lines_sync_attempts');
        });
    }

    public function down(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            $t->dropColumn('related_voucher_ids');
        });
    }
};
