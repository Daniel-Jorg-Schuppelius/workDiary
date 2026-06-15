<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_12_120000_create_bank_accounts_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eigene Bankkonten der Organisation (Feature 045, „Priorität 3").
 *
 * Die IBAN liegt verschlüsselt at-rest (`text`-Spalte, encrypted Cast, wie die
 * übrigen Bankdaten-PII der Anwendung). Für Eindeutigkeit und Auto-Zuordnung
 * eingehender Auszüge dient die plaintext-Spalte `iban_hash`
 * (SHA-256 der normalisierten IBAN — kein Klartext-Personenbezug).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('label', 120);
            $table->text('iban');                 // encrypted
            $table->string('iban_hash', 64);      // SHA-256 normalisierte IBAN (plaintext)
            $table->string('bic', 32)->nullable();
            $table->string('account_holder', 200)->nullable();
            $table->string('datev_account_no', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'iban_hash'], 'bank_accounts_org_iban_uq');
            $table->index(['organization_id', 'is_active'], 'bank_accounts_org_active_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('bank_accounts');
    }
};
