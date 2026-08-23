<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_09_100000_create_accounting_posting_rules_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 125, MVP-673: versionierte Buchungsregeln (Quelle + Rolle → Konto).
 *
 * Die Regel ordnet zu, sie entscheidet nicht: Beträge und Steueraufteilung
 * kommen aus dem eingefrorenen Beleg-Snapshot, die Regel sagt nur, auf
 * welches Konto das Ergebnis gehört. Stichtagsfähig und versioniert, damit
 * ein Kontenwechsel Altbuchungen nicht rückwirkend umdeutet.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_posting_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source_kind', 32);
            $table->string('role', 32);
            $table->unsignedBigInteger('accounting_account_id');
            $table->unsignedBigInteger('accounting_tax_code_id')->nullable();
            // Trefferkriterien als Merkmalsmenge; leer = Auffangregel.
            $table->json('match_criteria')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedSmallInteger('version')->default(1);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note', 191)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'source_kind', 'role', 'is_active'], 'acc_rule_org_kind_role_idx');

            $table->foreign('accounting_account_id', 'acc_rule_account_fk')
                ->references('id')->on('accounting_accounts')->cascadeOnDelete();
            $table->foreign('accounting_tax_code_id', 'acc_rule_taxcode_fk')
                ->references('id')->on('accounting_tax_codes')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_posting_rules');
    }
};
