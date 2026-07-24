<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_28_100000_create_customer_billing_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunden-Sonderkonditionen & Abrechnungskonto (Feature 098): je Kunde ein
 * Abrechnungsprofil (Konto- oder Rechnungs-Modus) mit Sätzen je Tätigkeit ×
 * Tagtyp, Monats-Statements mit Übertrags-Saldo (Vorbild flex_balances/
 * month_closures) und Zahlungen (manuell/Bank-Matching/Import).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('customer_billing_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations', indexName: 'fk_cba_org')->cascadeOnDelete();
            $table->foreignId('customer_id')
                ->constrained('customers', indexName: 'fk_cba_customer')->cascadeOnDelete();
            $table->string('mode', 20)->default('account');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('expected_monthly_amount', 10, 2)->nullable();
            $table->unsignedTinyInteger('workdays_per_week')->default(6);
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('customer_id', 'uq_cba_customer');
        });

        Schema::create('customer_billing_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations', indexName: 'fk_cbr_org')->cascadeOnDelete();
            $table->foreignId('customer_billing_agreement_id')
                ->constrained('customer_billing_agreements', indexName: 'fk_cbr_agreement')->cascadeOnDelete();
            $table->foreignId('activity_category_id')->nullable()
                ->constrained('activity_categories', indexName: 'fk_cbr_activity')->nullOnDelete();
            $table->string('day_type', 10)->default('weekday');
            $table->decimal('hourly_rate', 8, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->unique(
                ['customer_billing_agreement_id', 'activity_category_id', 'day_type', 'valid_from'],
                'uq_cbr_scope'
            );
        });

        Schema::create('customer_billing_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations', indexName: 'fk_cbs_org')->cascadeOnDelete();
            $table->foreignId('customer_billing_agreement_id')
                ->constrained('customer_billing_agreements', indexName: 'fk_cbs_agreement')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->integer('total_minutes')->default(0);
            $table->decimal('gross_value', 12, 2)->default(0);
            $table->decimal('payments_total', 12, 2)->default(0);
            $table->decimal('carry_in', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->boolean('locked')->default(false);
            $table->dateTime('locked_at')->nullable();
            $table->foreignId('locked_by_user_id')->nullable()
                ->constrained('users', indexName: 'fk_cbs_locked_by')->nullOnDelete();
            $table->json('totals')->nullable();
            $table->dateTime('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_billing_agreement_id', 'year', 'month'], 'uq_cbs_period');
            $table->index(['organization_id', 'year', 'month'], 'idx_cbs_org_period');
        });

        Schema::create('customer_account_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations', indexName: 'fk_cap_org')->cascadeOnDelete();
            $table->foreignId('customer_billing_agreement_id')
                ->constrained('customer_billing_agreements', indexName: 'fk_cap_agreement')->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('source', 10)->default('manual');
            $table->foreignId('bank_transaction_id')->nullable()
                ->constrained('bank_transactions', indexName: 'fk_cap_banktx')->nullOnDelete();
            $table->foreignId('payment_allocation_id')->nullable()
                ->constrained('payment_allocations', indexName: 'fk_cap_allocation')->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users', indexName: 'fk_cap_creator')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_billing_agreement_id', 'paid_on'], 'idx_cap_agr_date');
        });
    }

    public function down(): void {
        Schema::dropIfExists('customer_account_payments');
        Schema::dropIfExists('customer_billing_statements');
        Schema::dropIfExists('customer_billing_rates');
        Schema::dropIfExists('customer_billing_agreements');
    }
};
