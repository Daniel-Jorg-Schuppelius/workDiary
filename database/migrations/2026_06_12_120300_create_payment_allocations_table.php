<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_12_120300_create_payment_allocations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bestätigte Zahlungszuordnungen (Feature 045, „Priorität 3"). Verbindet einen
 * Bankumsatz mit einem fachlichen Ziel (Invoice|Expense, morph). SoftDelete
 * macht die Zuordnung reversibel, ohne den Bankumsatz zu verändern.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->string('allocatable_type');
            $table->unsignedBigInteger('allocatable_id');
            $table->decimal('amount', 14, 2);
            $table->string('kind', 16);                   // payment|partial|overpayment|reimbursement
            $table->string('note', 500)->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['allocatable_type', 'allocatable_id'], 'pay_alloc_target_idx');
            $table->index(['organization_id', 'bank_transaction_id'], 'pay_alloc_org_tx_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('payment_allocations');
    }
};
