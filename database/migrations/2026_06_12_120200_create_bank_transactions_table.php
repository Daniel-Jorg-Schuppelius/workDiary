<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_12_120200_create_bank_transactions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normalisierte Bankumsätze (Feature 045, „Priorität 3"). Personenbezogene
 * Felder (Gegenpartei-Name/-IBAN, Verwendungszweck) liegen verschlüsselt
 * at-rest (encrypted Casts, `text`-Spalten). Das Matching läuft ausschließlich
 * über die unverschlüsselten Ableitungen:
 *   - counterparty_iban_hash (SHA-256 normalisierte Gegenpartei-IBAN),
 *   - extracted_refs (aus Zweck/EREF herausgelöste Rechnungsnummern, KEINE PII),
 *   - amount / direction / dates.
 *
 * `fingerprint` (SHA-256 über Auszug+Zeile+Betrag+Datum+Refs) schützt vor
 * Dubletten beim Re-Import. Bankumsätze sind NIE editierbar (nur match_status).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('bank_statement_id')->constrained('bank_statements')->cascadeOnDelete();
            $table->unsignedInteger('line_index');
            $table->date('booking_date');
            $table->date('valuta_date')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('direction', 8);               // credit|debit (credit = Geldeingang)
            $table->string('currency', 3)->default('EUR');
            $table->string('end_to_end_id', 64)->nullable();
            $table->string('mandate_ref', 64)->nullable();
            $table->text('counterparty_name')->nullable();          // encrypted
            $table->text('counterparty_iban')->nullable();          // encrypted
            $table->string('counterparty_iban_hash', 64)->nullable(); // plaintext blind index
            $table->text('purpose')->nullable();                    // encrypted
            $table->json('extracted_refs')->nullable();             // plaintext Rechnungsnr-Kandidaten
            $table->boolean('is_reversal')->default(false);
            $table->string('fingerprint', 64);
            $table->string('match_status', 16)->default('unmatched');
            $table->timestamps();

            $table->unique(['organization_id', 'fingerprint'], 'bank_tx_org_fp_uq');
            $table->index(['bank_statement_id', 'line_index'], 'bank_tx_stmt_line_idx');
            $table->index(['organization_id', 'match_status'], 'bank_tx_org_status_idx');
            $table->index('counterparty_iban_hash', 'bank_tx_cp_iban_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('bank_transactions');
    }
};
