<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_12_120100_create_bank_statements_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Importierte Bankauszüge (Feature 045, „Priorität 3"). Originaldatei,
 * Auszug und normalisierte Umsätze bleiben getrennt nachvollziehbar.
 *
 * Dublettenschutz: `file_hash` (SHA-256 der Originaldatei) ist je Organisation
 * eindeutig. `statement_iban_hash` (plaintext) dient der Auto-Zuordnung zum
 * eigenen Bankkonto und enthält keinen Klartext-Personenbezug.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('bank_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('source_format', 16);          // camt053|mt940
            $table->string('file_path');
            $table->string('file_hash', 64);              // SHA-256 Originaldatei (Dublettenschutz)
            $table->string('statement_iban_hash', 64)->nullable();
            $table->decimal('opening_balance', 14, 2)->nullable();
            $table->decimal('closing_balance', 14, 2)->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedInteger('tx_count')->default(0);
            $table->string('balance_check', 16)->default('unknown'); // ok|mismatch|unknown
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'file_hash'], 'bank_stmt_org_file_uq');
            $table->index(['organization_id', 'bank_account_id'], 'bank_stmt_org_acct_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('bank_statements');
    }
};
