<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_08_100000_create_accounting_ledger_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 125, MVP-672: Kontenplan, Steuerkennzeichen und unveränderlicher
 * Buchungskern.
 *
 * Die Journalzeile ist bewusst zweispaltig (`debit`/`credit`) statt
 * vorzeichenbehaftet: Ein einzelnes signiertes Feld ist beim Lesen kürzer,
 * beim Prüfen aber schlechter — Soll- und Habensumme müssen je Buchung
 * getrennt aufgehen, und genau das ist die Invariante, die eine Buchhaltung
 * trägt.
 *
 * Kurze, explizite FK-/Index-Namen: die generierten Namen lägen bei diesen
 * Tabellennamen über der MySQL-Grenze von 64 Zeichen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 16);
            $table->string('name', 191);
            $table->string('type', 16);
            $table->string('normal_balance', 8);
            // Offene-Posten-Konto: Forderungen/Verbindlichkeiten je Gegenpartei.
            $table->boolean('is_open_item')->default(false);
            $table->boolean('is_bank')->default(false);
            $table->boolean('is_cash')->default(false);
            // Klärungskonto: nur durch bewusste Auswahl, nie als Auffangbecken.
            $table->boolean('is_clearing')->default(false);
            $table->unsignedBigInteger('default_tax_code_id')->nullable();
            $table->string('datev_account', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'acc_account_org_no_uq');
            $table->index(['organization_id', 'type', 'is_active'], 'acc_account_org_type_idx');
        });

        Schema::create('accounting_tax_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name', 191);
            $table->string('direction', 8);
            $table->decimal('rate', 5, 2)->default(0);
            // Verweist auf die Belegentscheidung (TaxRule-Kategorie), trifft sie nicht neu.
            $table->string('tax_category', 32)->nullable();
            $table->unsignedBigInteger('tax_account_id')->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code', 'valid_from'], 'acc_taxcode_org_code_uq');
            $table->index(['organization_id', 'is_active'], 'acc_taxcode_org_active_idx');

            $table->foreign('tax_account_id', 'acc_taxcode_account_fk')
                ->references('id')->on('accounting_accounts')->nullOnDelete();
        });

        Schema::table('accounting_accounts', function (Blueprint $table): void {
            $table->foreign('default_tax_code_id', 'acc_account_taxcode_fk')
                ->references('id')->on('accounting_tax_codes')->nullOnDelete();
        });

        Schema::create('accounting_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('accounting_fiscal_year_id');
            $table->unsignedBigInteger('accounting_period_id');
            // Lückenlos je Organisation, transaktional vergeben.
            $table->unsignedBigInteger('journal_no')->nullable();
            $table->date('booked_on');
            $table->date('document_on')->nullable();
            $table->string('status', 16)->default('draft');
            $table->string('memo', 191);
            $table->string('document_reference', 64)->nullable();
            $table->string('currency', 3)->default('EUR');
            // Quelle: Fachobjekt (polymorph) + Idempotenzschlüssel.
            $table->nullableMorphs('source', 'acc_entry_source_idx');
            $table->string('source_key', 191)->nullable();
            $table->string('rule_version', 32)->nullable();
            $table->json('snapshot')->nullable();
            $table->unsignedBigInteger('reverses_entry_id')->nullable();
            $table->unsignedBigInteger('reversed_by_entry_id')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'journal_no'], 'acc_entry_org_journal_uq');
            // Idempotenz: eine Quelle erzeugt höchstens eine Buchung.
            $table->unique(['organization_id', 'source_key'], 'acc_entry_org_source_uq');
            $table->index(['organization_id', 'booked_on'], 'acc_entry_org_date_idx');
            $table->index(['organization_id', 'status'], 'acc_entry_org_status_idx');

            $table->foreign('accounting_fiscal_year_id', 'acc_entry_fy_fk')
                ->references('id')->on('accounting_fiscal_years')->cascadeOnDelete();
            $table->foreign('accounting_period_id', 'acc_entry_period_fk')
                ->references('id')->on('accounting_periods')->cascadeOnDelete();
            $table->foreign('reverses_entry_id', 'acc_entry_reverses_fk')
                ->references('id')->on('accounting_entries')->nullOnDelete();
            $table->foreign('reversed_by_entry_id', 'acc_entry_reversed_by_fk')
                ->references('id')->on('accounting_entries')->nullOnDelete();
            $table->foreign('created_by', 'acc_entry_creator_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('posted_by', 'acc_entry_poster_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('accounting_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('accounting_entry_id');
            $table->unsignedSmallInteger('line_no');
            $table->unsignedBigInteger('accounting_account_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedBigInteger('accounting_tax_code_id')->nullable();
            $table->decimal('tax_amount', 15, 2)->nullable();
            // Gegenpartei (Kunde/Lieferant/Mitarbeiter) ohne Stammdaten-Duplikat.
            $table->nullableMorphs('counterparty', 'acc_line_cp_idx');
            // Optionale Analysebezüge — kein Kostenstellen-Parallelsystem.
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('cost_group', 16)->nullable();
            $table->string('memo', 191)->nullable();
            $table->timestamps();

            $table->unique(['accounting_entry_id', 'line_no'], 'acc_line_entry_no_uq');
            $table->index(['organization_id', 'accounting_account_id'], 'acc_line_org_account_idx');

            $table->foreign('accounting_entry_id', 'acc_line_entry_fk')
                ->references('id')->on('accounting_entries')->cascadeOnDelete();
            $table->foreign('accounting_account_id', 'acc_line_account_fk')
                ->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->foreign('accounting_tax_code_id', 'acc_line_taxcode_fk')
                ->references('id')->on('accounting_tax_codes')->nullOnDelete();
            $table->foreign('project_id', 'acc_line_project_fk')
                ->references('id')->on('projects')->nullOnDelete();
            $table->foreign('asset_id', 'acc_line_asset_fk')
                ->references('id')->on('assets')->nullOnDelete();
        });

        // Append-only Hash-Kette (Muster cash_entries/billing_transfer_events):
        // bewusst OHNE Fremdschlüssel — die Kette muss scope-frei prüfbar
        // bleiben und überdauert das Löschen von Buchung oder Organisation.
        Schema::create('accounting_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('accounting_entry_id')->nullable();
            $table->string('event', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('payload')->nullable();
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'accounting_entry_id'], 'acc_event_org_entry_idx');
            $table->index('event', 'acc_event_event_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_events');
        Schema::dropIfExists('accounting_entry_lines');
        Schema::dropIfExists('accounting_entries');
        Schema::table('accounting_accounts', function (Blueprint $table): void {
            $table->dropForeign('acc_account_taxcode_fk');
        });
        Schema::dropIfExists('accounting_tax_codes');
        Schema::dropIfExists('accounting_accounts');
    }
};
