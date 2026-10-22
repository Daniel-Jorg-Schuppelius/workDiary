<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_130000_create_self_billing_credits_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gutschriftsverfahren (MVP-1280): Die Organisation rechnet als
 * Leistungsempfängerin über eine Leistung des Lieferanten ab (§ 14 Abs. 2
 * Satz 2 UStG). Eigener Beleg mit eigenem Nummernkreis; Positionen nach dem
 * Belegpositions-Vertrag. Den Lieferanten löschen sperrt der
 * PartyDeletionGuard, solange ein Beleg auf ihn zeigt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->date('self_billing_agreed_on')->nullable()->after('tax_number');
        });

        Schema::create('self_billing_credits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'sbc_org_fk')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers', indexName: 'sbc_supplier_fk')->cascadeOnDelete();
            $table->string('number', 40)->nullable();
            $table->string('status', 12)->default('draft');
            $table->date('issued_on')->nullable();
            $table->date('service_from')->nullable();
            $table->date('service_until')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('incoming_e_invoice_id')->nullable()->constrained('incoming_einvoices', indexName: 'sbc_incoming_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'sbc_created_by_fk')->nullOnDelete();
            $table->foreignId('issuer_user_id')->nullable()->constrained('users', indexName: 'sbc_issuer_fk')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('canceller_user_id')->nullable()->constrained('users', indexName: 'sbc_canceller_fk')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'sbc_number_unique');
            $table->index(['organization_id', 'status'], 'sbc_org_status_idx');
        });

        Schema::create('self_billing_credit_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'sbcl_org_fk')->cascadeOnDelete();
            $table->foreignId('self_billing_credit_id')->constrained('self_billing_credits', indexName: 'sbcl_credit_fk')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('description', 500);
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 15, 4);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('self_billing_credit_lines');
        Schema::dropIfExists('self_billing_credits');
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn('self_billing_agreed_on');
        });
    }
};
