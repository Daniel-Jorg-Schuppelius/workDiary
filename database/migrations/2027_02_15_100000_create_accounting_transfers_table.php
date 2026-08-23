<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_15_100000_create_accounting_transfers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interne Umbuchung Bank ↔ Kasse (Feature 125, MVP-681).
 *
 * Der Vorgang koppelt beide Seiten: Ein Bankumsatz und die zugehörige
 * Kassenbuchung gehören zu **einer** Buchung. Ohne diese Klammer würde jede
 * Seite einzeln in der Buchungs-Inbox auftauchen und der Betrag zweimal im
 * Ergebnis landen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('booked_on');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->unsignedBigInteger('from_account_id');
            $table->unsignedBigInteger('to_account_id');
            $table->string('note', 500);
            // Gekoppelte Belege: Sie gelten über diesen Vorgang als gebucht.
            $table->nullableMorphs('from_source', 'acc_transfer_from_src_idx');
            $table->nullableMorphs('to_source', 'acc_transfer_to_src_idx');
            $table->unsignedBigInteger('accounting_entry_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'booked_on'], 'acc_transfer_org_date_idx');
            $table->foreign('from_account_id', 'acc_transfer_from_fk')
                ->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->foreign('to_account_id', 'acc_transfer_to_fk')
                ->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->foreign('accounting_entry_id', 'acc_transfer_entry_fk')
                ->references('id')->on('accounting_entries')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_transfers');
    }
};
