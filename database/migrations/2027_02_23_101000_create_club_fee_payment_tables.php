<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_101000_create_club_fee_payment_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zahlungen, Mahnung und Einzug (Feature 159, MVP-851): Beitragszahlungen je
 * Konto und Forderung (manuell, Bank, Einzug, Rücklastschrift, Guthaben),
 * Mahnhistorie, Portalzugang des Zahlungspflichtigen, Mandat am Konto und
 * die Reservierung einer Forderung durch einen aktiven Einzugsversuch.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_fee_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_fee_account_id')->constrained('club_fee_accounts')->cascadeOnDelete();
            // null = nicht zugeordnetes Guthaben des Kontos.
            $table->foreignId('club_fee_claim_id')->nullable()->constrained('club_fee_claims')->nullOnDelete();
            $table->decimal('amount', 12, 2); // negativ = Rücklastschrift/Verrechnung
            $table->string('currency', 3)->default('EUR');
            $table->date('paid_on');
            $table->string('method', 16)->default('transfer');
            $table->string('source', 16)->default('manual');
            $table->string('reference', 140)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
            $table->foreignId('payment_allocation_id')->nullable()->constrained('payment_allocations')->nullOnDelete();
            $table->foreignId('payment_run_item_id')->nullable()->constrained('payment_run_items')->nullOnDelete();
            // Rücklastschrift verweist auf die kompensierte Zahlung — je Zahlung höchstens eine.
            $table->foreignId('chargeback_of_id')->nullable()->constrained('club_fee_payments')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('chargeback_of_id', 'club_fee_pay_chargeback_uq');
            $table->index(['club_fee_account_id', 'paid_on'], 'club_fee_pay_account_paid_idx');
            $table->index(['club_fee_claim_id'], 'club_fee_pay_claim_idx');
        });

        Schema::create('club_fee_dunnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_fee_claim_id')->constrained('club_fee_claims')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->date('issued_on');
            $table->date('pay_until')->nullable();
            $table->decimal('fee', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->foreignId('fee_claim_id')->nullable()->constrained('club_fee_claims')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['club_fee_claim_id', 'level'], 'club_fee_dun_claim_level_idx');
        });

        Schema::table('club_fee_accounts', function (Blueprint $table): void {
            // Ausdrücklich berechtigte zahlende Person (Portal) — nicht aus Vertretungsrechten abgeleitet.
            $table->foreignId('user_id')->nullable()->after('email')->constrained('users')->nullOnDelete();
            $table->foreignId('sepa_mandate_id')->nullable()->after('user_id')->constrained('sepa_mandates')->nullOnDelete();
        });

        Schema::table('club_fee_claims', function (Blueprint $table): void {
            // Aktiver Einzugsversuch reserviert den Betrag gegen erneuten Einzug.
            $table->foreignId('payment_run_item_id')->nullable()->after('dunning_block_reason')->constrained('payment_run_items')->nullOnDelete();
            $table->unsignedSmallInteger('collection_attempts')->default(0)->after('payment_run_item_id');
            $table->timestamp('collection_blocked_at')->nullable()->after('collection_attempts');
            $table->string('collection_block_reason', 255)->nullable()->after('collection_blocked_at');
        });
    }

    public function down(): void {
        Schema::table('club_fee_claims', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_run_item_id');
            $table->dropColumn(['collection_attempts', 'collection_blocked_at', 'collection_block_reason']);
        });
        Schema::table('club_fee_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sepa_mandate_id');
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::dropIfExists('club_fee_dunnings');
        Schema::dropIfExists('club_fee_payments');
    }
};
