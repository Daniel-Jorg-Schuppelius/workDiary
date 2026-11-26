<?php
/*
 * Created on   : Thu Nov 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_05_01_100000_add_awaited_invoice_to_incoming_einvoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rechnungseingang (MVP-1810): ein Beleg (Mahnung) wartet auf eine Rechnung,
 * die noch nicht eingegangen ist, und hängt sich an, sobald sie da ist.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->string('awaited_invoice_number', 64)->nullable();
            $table->string('awaited_invoice_number_key', 64)->nullable();
            $table->index(['organization_id', 'awaited_invoice_number_key'], 'incoming_einvoices_org_awaited_idx');
        });
    }

    public function down(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropIndex('incoming_einvoices_org_awaited_idx');
            $table->dropColumn(['awaited_invoice_number', 'awaited_invoice_number_key']);
        });
    }
};
