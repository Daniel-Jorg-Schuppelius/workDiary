<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_250000_add_reverse_charge_recipient_columns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Steuerschuld des Empfängers (MVP-1621/1622): Vorsteuerkonto, Vorsteuer-
 * Kennziffer, Steuerfall und DATEV-Steuerschlüssel am Kennzeichen; Steuerfall
 * an Eingangsrechnung und Lieferant; § 13b als Leistender am Kunden.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('accounting_tax_codes', function (Blueprint $table): void {
            $table->unsignedBigInteger('input_tax_account_id')->nullable()->after('tax_account_id');
            $table->string('ustva_input_tax_field', 8)->nullable()->after('ustva_tax_field');
            $table->string('tax_case', 32)->nullable()->after('tax_category');
            $table->string('datev_tax_key', 4)->nullable()->after('ustva_input_tax_field');

            $table->foreign('input_tax_account_id', 'acc_taxcode_input_account_fk')
                ->references('id')->on('accounting_accounts')->nullOnDelete();
        });

        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->string('tax_case', 32)->nullable()->after('amount_gross');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('incoming_tax_case', 32)->nullable()->after('tax_number');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('is_reverse_charge_recipient')->default(false)->after('vat_id');
        });
    }

    public function down(): void {
        Schema::table('accounting_tax_codes', function (Blueprint $table): void {
            $table->dropForeign('acc_taxcode_input_account_fk');
            $table->dropColumn(['input_tax_account_id', 'ustva_input_tax_field', 'tax_case', 'datev_tax_key']);
        });
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropColumn('tax_case');
        });
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn('incoming_tax_case');
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('is_reverse_charge_recipient');
        });
    }
};
