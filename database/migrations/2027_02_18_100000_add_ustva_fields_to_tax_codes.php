<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_18_100000_add_ustva_fields_to_tax_codes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kennziffern der Umsatzsteuer-Voranmeldung (Feature 125, MVP-688).
 *
 * Die Kennziffer hängt am **Steuerkennzeichen**, nicht am Konto: Das
 * Kennzeichen kennt bereits Richtung, Satz und Steuerkonto — Bemessungs-
 * grundlage (81/86/41) und Steuerbetrag (66/61/62) gehören in dieselbe Zeile.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('accounting_tax_codes', function (Blueprint $table): void {
            $table->string('ustva_base_field', 8)->nullable()->after('tax_account_id');
            $table->string('ustva_tax_field', 8)->nullable()->after('ustva_base_field');
        });
    }

    public function down(): void {
        Schema::table('accounting_tax_codes', function (Blueprint $table): void {
            $table->dropColumn(['ustva_base_field', 'ustva_tax_field']);
        });
    }
};
