<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_06_120400_add_external_number_to_invoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lexoffice-Belegnummern für Rechnungen:
 *  - number wird nullable (Entwürfe vor Lexoffice-Push haben ggf. keine
 *    endgültige Nummer)
 *  - external_number : von Lexoffice vergebene Belegnummer (voucherNumber)
 *  - number_source   : 'local' | 'lexoffice'
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('number', 64)->nullable()->change();
            $table->string('external_number', 64)->nullable()->after('number');
            $table->string('number_source', 16)->default('local')->after('external_number');
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['external_number', 'number_source']);
            $table->string('number', 64)->nullable(false)->change();
        });
    }
};
