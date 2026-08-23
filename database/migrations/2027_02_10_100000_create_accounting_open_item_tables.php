<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_10_100000_create_accounting_open_item_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 125, MVP-674: offene Posten und ihre Ausgleiche.
 *
 * Der offene Posten ist eine **Projektion** der Festbuchung, keine zweite
 * Wahrheit: Er entsteht aus einer Buchungszeile auf einem OPOS-Konto und
 * verweist auf sie zurück. Die wirtschaftliche Quelle bleibt der Beleg.
 *
 * Ausgleiche sind unveränderliche Zuordnungen (append-only): Ein Rückläufer
 * löscht nichts, er erzeugt eine Gegenbewegung und öffnet den Posten wieder.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_open_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('accounting_entry_id');
            // Eine Buchungszeile erzeugt höchstens einen offenen Posten.
            $table->unsignedBigInteger('accounting_entry_line_id');
            $table->unsignedBigInteger('accounting_account_id');
            $table->string('direction', 16);
            $table->string('status', 24)->default('open');
            $table->nullableMorphs('counterparty', 'acc_opos_cp_idx');
            // Quelle des Belegs (Invoice, IncomingEInvoice, …) für den Drilldown.
            $table->nullableMorphs('source', 'acc_opos_source_idx');
            $table->string('document_reference', 64)->nullable();
            $table->date('document_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('original_amount', 15, 2);
            $table->decimal('open_amount', 15, 2);
            $table->dateTime('settled_at')->nullable();
            $table->timestamps();

            $table->unique('accounting_entry_line_id', 'acc_opos_line_uq');
            $table->index(['organization_id', 'direction', 'status'], 'acc_opos_org_dir_status_idx');
            $table->index(['organization_id', 'due_date'], 'acc_opos_org_due_idx');

            $table->foreign('accounting_entry_id', 'acc_opos_entry_fk')
                ->references('id')->on('accounting_entries')->cascadeOnDelete();
            $table->foreign('accounting_entry_line_id', 'acc_opos_line_fk')
                ->references('id')->on('accounting_entry_lines')->cascadeOnDelete();
            $table->foreign('accounting_account_id', 'acc_opos_account_fk')
                ->references('id')->on('accounting_accounts')->restrictOnDelete();
        });

        Schema::create('accounting_open_item_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('accounting_open_item_id');
            // Buchung, die den Ausgleich trägt (Zahlung, Skonto, Gegenbewegung).
            $table->unsignedBigInteger('accounting_entry_id')->nullable();
            $table->string('kind', 24);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('booked_on');
            // Ursprung im vorhandenen Zahlungsabgleich — nicht nachgebaut, konsumiert.
            $table->unsignedBigInteger('payment_allocation_id')->nullable();
            $table->unsignedBigInteger('reverses_settlement_id')->nullable();
            $table->string('note', 191)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'accounting_open_item_id'], 'acc_settle_org_item_idx');
            $table->index('payment_allocation_id', 'acc_settle_alloc_idx');

            $table->foreign('accounting_open_item_id', 'acc_settle_item_fk')
                ->references('id')->on('accounting_open_items')->cascadeOnDelete();
            $table->foreign('accounting_entry_id', 'acc_settle_entry_fk')
                ->references('id')->on('accounting_entries')->nullOnDelete();
            $table->foreign('reverses_settlement_id', 'acc_settle_reverses_fk')
                ->references('id')->on('accounting_open_item_settlements')->nullOnDelete();
            $table->foreign('created_by', 'acc_settle_creator_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_open_item_settlements');
        Schema::dropIfExists('accounting_open_items');
    }
};
