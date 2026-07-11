<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_04_120000_create_asset_finance_module_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 074 (Phase 26, MVP-270–281): Leasing, Finanzierung und
 * Asset-Verträge. Entscheidung D11: eigene, leasingspezifische Akte mit
 * Konditionen-Snapshot und Fristenkalender — KEIN generisches CLM.
 * Ist-Werte werden nur referenziert (Eingangsrechnung/Zähler), nie gebucht;
 * Bilanzierung und steuerliche Zurechnung bleiben beim Rechnungswesen (W11).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('asset_finance_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('kind', 30);
            $table->string('status', 20)->default('draft');
            $table->string('partner_name');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_no')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('notice_period_days')->nullable();
            $table->string('payment_rhythm', 20)->default('monthly');
            // Vertrauliche Konditionen (Rechte: assetFinance.finance)
            $table->decimal('rate_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('special_payment', 12, 2)->nullable();
            $table->decimal('residual_value', 12, 2)->nullable();
            $table->decimal('purchase_option_amount', 12, 2)->nullable();
            // P2: eingefrorene Konditionen zum Abschluss-/Änderungszeitpunkt
            $table->json('terms_snapshot')->nullable();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_center_label')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('insurance_note')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'af_contracts_org_number_unique');
            $table->index(['organization_id', 'status', 'ends_on'], 'af_contracts_org_status_end_idx');
        });

        Schema::create('asset_finance_contract_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_finance_contract_id')->constrained('asset_finance_contracts', indexName: 'af_contract_assets_contract_fk')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['asset_finance_contract_id', 'asset_id'], 'af_contract_assets_unique');
            $table->index(['organization_id', 'asset_id'], 'af_contract_assets_org_asset_idx');
        });

        Schema::create('asset_finance_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_finance_contract_id')->constrained('asset_finance_contracts', indexName: 'af_terms_contract_fk')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('label');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'asset_finance_contract_id'], 'af_terms_org_contract_idx');
        });

        Schema::create('asset_finance_rate_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_finance_contract_id')->constrained('asset_finance_contracts', indexName: 'af_schedules_contract_fk')->cascadeOnDelete();
            $table->date('due_on');
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('planned');
            // Ist-Wert nur als Referenz (D11) — nie gebucht
            $table->foreignId('incoming_einvoice_id')->nullable()->constrained('incoming_einvoices')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'asset_finance_contract_id', 'due_on'], 'af_schedules_org_contract_idx');
        });

        Schema::create('asset_finance_deadlines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_finance_contract_id')->constrained('asset_finance_contracts', indexName: 'af_deadlines_contract_fk')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->date('due_on');
            $table->unsignedInteger('warn_days_before')->default(30);
            $table->string('status', 20)->default('open');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->dateTime('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'due_on'], 'af_deadlines_org_status_idx');
        });

        Schema::create('asset_finance_usage_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_finance_contract_id')->constrained('asset_finance_contracts', indexName: 'af_limits_contract_fk')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->decimal('limit_value', 14, 2);
            $table->string('period', 20)->default('total');
            $table->decimal('overrun_fee_per_unit', 12, 4)->nullable();
            // Ist-Wert: manuell erfasst oder aus Zählerständen übernommen
            $table->decimal('actual_value', 14, 2)->nullable();
            $table->dateTime('actual_recorded_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'asset_finance_contract_id'], 'af_limits_org_contract_idx');
        });

        Schema::create('asset_finance_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_finance_contract_id')->constrained('asset_finance_contracts', indexName: 'af_options_contract_fk')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->date('exercisable_from')->nullable();
            $table->date('exercisable_until')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->dateTime('exercised_at')->nullable();
            $table->foreignId('exercised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'asset_finance_contract_id'], 'af_options_org_contract_idx');
        });

        Schema::create('asset_finance_end_processes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_finance_contract_id')->constrained('asset_finance_contracts', indexName: 'af_ends_contract_fk')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('status', 20)->default('draft');
            $table->text('condition_note')->nullable();
            $table->decimal('meter_value', 18, 4)->nullable();
            $table->decimal('operating_hours', 12, 2)->nullable();
            $table->text('damages')->nullable();
            $table->text('accessories')->nullable();
            // Nachberechnung/Erstattung aus der Endabrechnung (nur Referenzwert)
            $table->decimal('follow_up_amount', 12, 2)->nullable();
            $table->date('new_ends_on')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'asset_finance_contract_id'], 'af_ends_org_contract_idx');
        });

        Schema::create('asset_finance_cost_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_finance_contract_id')->nullable()->constrained('asset_finance_contracts', indexName: 'af_costs_contract_fk')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'asset_finance_contract_id'], 'af_costs_org_contract_idx');
        });

        Schema::create('asset_finance_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('asset_finance_report_snapshots');
        Schema::dropIfExists('asset_finance_cost_snapshots');
        Schema::dropIfExists('asset_finance_end_processes');
        Schema::dropIfExists('asset_finance_options');
        Schema::dropIfExists('asset_finance_usage_limits');
        Schema::dropIfExists('asset_finance_deadlines');
        Schema::dropIfExists('asset_finance_rate_schedules');
        Schema::dropIfExists('asset_finance_terms');
        Schema::dropIfExists('asset_finance_contract_assets');
        Schema::dropIfExists('asset_finance_contracts');
    }
};
