<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_31_100000_create_payment_runs_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEPA-Zahlungsausgang (Feature 120, MVP-609).
 *
 * Ein Zahllauf ist ein revisionsrelevanter Vorgang: Wer hat wann welche Datei
 * mit welchen Positionen erzeugt. Die erzeugte Datei wird archiviert
 * (`document_id` + `file_sha256`) — ein zweiter Download liefert dieselbe
 * Datei, nie eine neue mit abweichender Message-ID.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('sepa_mandates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 35);
            $table->string('kind', 20)->default('recurring');
            $table->string('status', 20)->default('active');
            $table->date('signed_on');
            $table->date('last_collected_on')->nullable();
            $table->date('revoked_on')->nullable();
            $table->text('iban');
            $table->string('iban_hash', 64)->nullable();
            $table->text('bic')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            // Die Mandatsreferenz ist der Schlüssel gegenüber der Bank — sie
            // muss je Gläubiger (Organisation) eindeutig sein.
            $table->unique(['organization_id', 'reference'], 'sepa_mandate_org_ref_unique');
            $table->index(['organization_id', 'customer_id'], 'sepa_mandate_org_cust_idx');
        });

        Schema::create('payment_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->string('kind', 20)->default('credit_transfer');
            $table->string('status', 20)->default('draft');
            $table->string('label')->nullable();
            $table->date('execution_date');
            $table->string('message_id', 35)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('total', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('file_sha256', 64)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'payment_run_org_status_idx');
            $table->index(['organization_id', 'execution_date'], 'payment_run_org_exec_idx');
            $table->unique(['organization_id', 'message_id'], 'payment_run_org_msg_unique');
        });

        Schema::create('payment_run_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_run_id')->constrained('payment_runs')->cascadeOnDelete();
            $table->foreignId('incoming_einvoice_id')->nullable()->constrained('incoming_einvoices')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('sepa_mandate_id')->nullable()->constrained('sepa_mandates')->nullOnDelete();
            $table->string('party_name', 70);
            $table->text('iban');
            $table->text('bic')->nullable();
            $table->decimal('amount', 15, 2);
            // Der Bruttobetrag der Rechnung bleibt neben dem Zahlbetrag stehen:
            // Skontoabzug und Kürzung sollen sichtbar sein, nicht errechenbar.
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->string('deduction_reason')->nullable();
            $table->string('reference', 140);
            $table->string('end_to_end_id', 35)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'payment_run_id'], 'payment_run_item_org_run_idx');
            // Eine Eingangsrechnung darf nie zweimal in denselben Lauf.
            $table->unique(['payment_run_id', 'incoming_einvoice_id'], 'payment_run_item_invoice_unique');
        });

        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            // Zahlungsrelevante Felder aus dem Original (BT-84/BT-86, Zahlungsbedingungen):
            // beim Empfang denormalisiert, damit der Zahlungsvorschlag nicht
            // jede Datei erneut parsen muss.
            $table->string('creditor_iban', 40)->nullable()->after('amount_gross');
            $table->string('creditor_bic', 20)->nullable()->after('creditor_iban');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('creditor_bic');
            $table->unsignedSmallInteger('discount_days')->nullable()->after('discount_percent');
            $table->foreignId('paid_in_run_id')->nullable()->after('discount_days')
                ->constrained('payment_runs')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('paid_in_run_id');
            $table->dropColumn(['creditor_iban', 'creditor_bic', 'discount_percent', 'discount_days']);
        });
        Schema::dropIfExists('payment_run_items');
        Schema::dropIfExists('payment_runs');
        Schema::dropIfExists('sepa_mandates');
    }
};
