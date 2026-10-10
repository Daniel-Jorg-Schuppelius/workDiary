<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_16_100200_add_contact_name_to_lexoffice_vouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-1117 (E12): Belege nicht verknüpfter Kontakte kommen in den Spiegel und
 * brauchen den Kontaktnamen der voucherlist (`contactName`). Der Lauf ohne
 * Kontakt setzt beim jüngsten `synced_at` seiner Belege auf; zurückgesetzt
 * holt er einmal alles und damit auch die älteren Belege dieser Kontakte.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            $t->string('contact_name', 255)->nullable()->after('recipient_name');
        });

        DB::table('lexoffice_vouchers')->whereNull('contact_external_id')->update(['synced_at' => null]);
    }

    public function down(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            $t->dropColumn('contact_name');
        });
    }
};
