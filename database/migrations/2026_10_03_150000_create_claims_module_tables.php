<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_03_150000_create_claims_module_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 072 (Phase 24, MVP-246–257): Reklamation, Gewährleistung und
 * Rückläufer. Fallakten führen Status/Fristen/Nachweise; führende
 * Fachmodule (Auftrag/Asset/Artikel/Lager/Faktura) bleiben unangetastet.
 * Entscheidung D1: kein neuer Belegtyp — kaufmännische Art lebt in
 * claim_financial_outcomes.kind, der Beleg trägt nur reason_kind.
 * Entscheidung D3: Ursachencodes über classifications (kein Parallelkatalog).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('claim_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('status', 20)->default('received');
            $table->string('source', 20)->default('manual');
            $table->string('priority', 10)->default('normal');
            $table->string('severity', 10)->default('minor');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();
            $table->boolean('is_b2b')->default(false);
            $table->dateTime('reported_at');
            // § 377 HGB: Rügedatum im B2B-Fall (Genehmigungsfiktion!)
            $table->date('complaint_notice_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Betroffene Objekte (führende Module bleiben Datenherren)
            $table->foreignId('diary_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('protocol_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_serial_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_no')->nullable();
            // D3: Ursachencodes über bestehende Klassifikationsdomänen
            $table->foreignId('defect_type_classification_id')->nullable()->constrained('classifications')->nullOnDelete();
            $table->foreignId('root_cause_classification_id')->nullable()->constrained('classifications')->nullOnDelete();
            $table->foreignId('goodwill_reason_classification_id')->nullable()->constrained('classifications')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('anonymized_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'claim_cases_org_number_unique');
            $table->index(['organization_id', 'status', 'due_at'], 'claim_cases_org_status_due_idx');
            $table->index(['organization_id', 'customer_id'], 'claim_cases_org_customer_idx');
        });

        Schema::create('claim_case_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_case_id')->constrained()->cascadeOnDelete();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->string('role', 20)->default('affected');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id'], 'claim_links_linkable_idx');
        });

        Schema::create('claim_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_case_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('other');
            $table->string('title');
            $table->text('note')->nullable();
            $table->string('evidencable_type')->nullable();
            $table->unsignedBigInteger('evidencable_id')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->index(['evidencable_type', 'evidencable_id'], 'claim_evidence_source_idx');
        });

        Schema::create('claim_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_case_id')->constrained()->cascadeOnDelete();
            $table->string('claim_kind', 30);
            $table->string('verdict', 20);
            $table->text('justification');
            // P2-Snapshot: Fristen-/Seriennummern-/Vertragsfakten zum Zeitpunkt
            $table->json('snapshot')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('assessed_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('assessed_at');
            $table->timestamps();
        });

        Schema::create('claim_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_case_id')->constrained()->cascadeOnDelete();
            $table->string('decision', 20);
            $table->text('justification');
            $table->json('snapshot')->nullable();
            $table->foreignId('decided_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('decided_at');
            $table->timestamps();
        });

        Schema::create('claim_rma_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_case_id')->constrained()->cascadeOnDelete();
            $table->string('rma_number', 40);
            $table->string('status', 20)->default('announced');
            $table->date('expected_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_serial_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_no')->nullable();
            $table->decimal('qty', 12, 4)->nullable();
            // Quarantäne als Bestandszustand (quality/blocked/damaged)
            $table->string('stock_state', 20)->nullable();
            $table->text('condition_note')->nullable();
            $table->string('disposition', 30)->nullable();
            $table->text('disposition_note')->nullable();
            $table->dateTime('disposed_at')->nullable();
            $table->foreignId('disposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'rma_number'], 'claim_rma_org_number_unique');
        });

        Schema::create('claim_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_rma_return_id')->constrained()->cascadeOnDelete();
            $table->string('result', 30);
            $table->text('findings')->nullable();
            $table->boolean('serial_checked')->default(false);
            $table->string('serial_check_result')->nullable();
            $table->foreignId('inspected_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('inspected_at');
            $table->timestamps();
        });

        Schema::create('claim_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_case_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('status', 20)->default('planned');
            $table->string('title');
            $table->text('note')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('done_at')->nullable();
            $table->string('follow_up_type')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['follow_up_type', 'follow_up_id'], 'claim_actions_follow_up_idx');
        });

        Schema::create('claim_financial_outcomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_case_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('status', 20)->default('proposed');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('result_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            // Belegnummer des führenden externen Systems (Lexoffice/DATEV),
            // wenn die Hoheit extern liegt und kein lokaler Beleg entsteht.
            $table->string('external_reference')->nullable();
            $table->text('justification');
            $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('executed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('claim_supplier_recourses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('incoming_einvoice_id')->nullable()->constrained('incoming_einvoices')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_no')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('external_reference')->nullable();
            $table->text('warranty_terms')->nullable();
            $table->decimal('amount_claimed', 12, 2)->nullable();
            $table->decimal('amount_recovered', 12, 2)->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('response_due_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->text('outcome_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('claim_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // D1: strukturierter Grund am Faktura-Beleg statt neuem Belegtyp
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('reason_kind', 40)->nullable()->after('cancel_reason');
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('reason_kind');
        });
        Schema::dropIfExists('claim_report_snapshots');
        Schema::dropIfExists('claim_supplier_recourses');
        Schema::dropIfExists('claim_financial_outcomes');
        Schema::dropIfExists('claim_actions');
        Schema::dropIfExists('claim_inspections');
        Schema::dropIfExists('claim_rma_returns');
        Schema::dropIfExists('claim_decisions');
        Schema::dropIfExists('claim_assessments');
        Schema::dropIfExists('claim_evidence');
        Schema::dropIfExists('claim_case_links');
        Schema::dropIfExists('claim_cases');
    }
};
