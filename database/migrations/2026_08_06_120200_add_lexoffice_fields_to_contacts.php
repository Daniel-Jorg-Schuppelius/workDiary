<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_06_120200_add_lexoffice_fields_to_contacts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ergänzt Customer/Supplier um Lexoffice-Stammdatenfelder:
 *  - tax_number            : Steuernummer (getrennt von vat_id = USt-IdNr.)
 *  - lexoffice_contact_number: offizielle Kontaktnummer aus Lexoffice
 *  - number_source         : 'local' | 'lexoffice' — Hoheit über die `number`
 *
 * Bei Suppliers existiert `vendor_number` bereits als Lexoffice-Nummer.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('tax_number', 64)->nullable()->after('vat_id');
            $table->string('lexoffice_contact_number', 64)->nullable()->after('number');
            $table->string('number_source', 16)->default('local')->after('lexoffice_contact_number');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('tax_number', 64)->nullable()->after('vat_id');
            $table->string('number_source', 16)->default('local')->after('vendor_number');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['tax_number', 'lexoffice_contact_number', 'number_source']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['tax_number', 'number_source']);
        });
    }
};
