<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_100300_add_creditor_iban_confirmation_to_incoming_einvoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vollscan 2026-08-23, E3: Weicht die Rechnungs-IBAN vom Lieferanten-Stammsatz
 * ab, blockt der Zahlungsvorschlag (`iban_differs`), bis ein Berechtigter die
 * Abweichung ausdrücklich — und auditiert — bestätigt hat.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->string('seller_vat_id', 32)->nullable()->after('seller_name');
            $table->timestamp('creditor_iban_confirmed_at')->nullable()->after('creditor_bic');
            $table->foreignId('creditor_iban_confirmed_by')->nullable()->after('creditor_iban_confirmed_at')
                ->constrained('users', 'id', 'fk_inc_einv_iban_confirmed_by')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('creditor_iban_confirmed_by');
            $table->dropColumn(['creditor_iban_confirmed_at', 'seller_vat_id']);
        });
    }
};
