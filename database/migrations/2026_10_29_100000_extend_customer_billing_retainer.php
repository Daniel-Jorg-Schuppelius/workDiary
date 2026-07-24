<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_29_100000_extend_customer_billing_retainer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 098 (Retainer-Erweiterung): verknüpft je Monat die an Lexoffice
 * übergebene Pauschalrechnung (Idempotenz-Marker) und macht den Zahlungs-
 * Rücksync idempotent über die Lexoffice-Voucher-UUID.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customer_billing_statements', function (Blueprint $table): void {
            $table->foreignId('retainer_invoice_id')->nullable()
                ->constrained('invoices', indexName: 'fk_cbs_retainer_invoice')->nullOnDelete();
        });

        Schema::table('customer_account_payments', function (Blueprint $table): void {
            // Lexoffice-Voucher-UUID (bzw. Spitzabrechnungs-UUID) — Dedup-Anker
            // für den idempotenten Zahlstatus-Rücksync. NULL für manuelle/Bank-/
            // Import-Zeilen (MySQL erlaubt mehrere NULL im Unique-Index).
            $table->string('source_reference', 64)->nullable()->after('source');
            $table->unique(
                ['customer_billing_agreement_id', 'source', 'source_reference'],
                'uq_cap_source_ref'
            );
        });
    }

    public function down(): void {
        Schema::table('customer_account_payments', function (Blueprint $table): void {
            $table->dropUnique('uq_cap_source_ref');
            $table->dropColumn('source_reference');
        });

        Schema::table('customer_billing_statements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('retainer_invoice_id');
        });
    }
};
