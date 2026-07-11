<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_03_110000_create_investments_module_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 069 (Phase 20, MVP-199–210): Investitionsplanung —
 * Investitionsakten mit Variantenvergleich, versionierten Budgetanträgen
 * (Freigabe-Snapshot, keine stille Erhöhung), Umsetzungs-Verknüpfungen,
 * Ist-Wert-Projektion, Abweichungsmanagement und Nachbewertung.
 * Dazu Kostenstellen-Stammdaten (Flexibilitätsplan D2: nullable FK +
 * Label-Fallback) und Anschaffungsdaten am Asset (Recherche-Lücke).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cc_org_fk')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('label', 200);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'cc_org_code_unique');
        });

        Schema::create('investment_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'inv_org_fk')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('category', 20)->default('replacement'); // replacement|expansion|project|machine|it|infrastructure|inventory|compliance
            $table->text('reason')->nullable(); // Anlass
            $table->text('objective')->nullable(); // Ziel/Nutzenannahme
            $table->string('urgency', 10)->default('medium'); // low|medium|high
            $table->text('risk_note')->nullable();
            $table->string('status', 20)->default('idea'); // idea|screening|comparison|budget_request|in_approval|approved|rejected|deferred|in_progress|completed|cancelled|post_review
            $table->foreignId('responsible_user_id')->nullable()->constrained('users', indexName: 'inv_resp_fk')->nullOnDelete();
            // Kostenstelle (D2): Stammdaten-FK MIT Label-Fallback.
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers', indexName: 'inv_cc_fk')->nullOnDelete();
            $table->string('cost_center_label', 200)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects', indexName: 'inv_project_fk')->nullOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'inv_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'inv_org_status_idx');
        });

        Schema::create('investment_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'invo_org_fk')->cascadeOnDelete();
            $table->foreignId('investment_case_id')->constrained('investment_cases', indexName: 'invo_case_fk')->cascadeOnDelete();
            $table->string('title', 200);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers', indexName: 'invo_supplier_fk')->nullOnDelete();
            $table->decimal('one_time_cost', 14, 2)->default(0);
            $table->decimal('recurring_cost_yearly', 14, 2)->default(0);
            $table->unsignedSmallInteger('delivery_weeks')->nullable();
            $table->unsignedSmallInteger('useful_life_years')->nullable();
            $table->unsignedTinyInteger('quality_score')->nullable(); // 1–5
            $table->string('risk_note', 1000)->nullable();
            $table->boolean('recommended')->default(false);
            $table->string('note', 1000)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents', indexName: 'invo_document_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('investment_budget_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'invbr_org_fk')->cascadeOnDelete();
            $table->foreignId('investment_case_id')->constrained('investment_cases', indexName: 'invbr_case_fk')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->decimal('amount', 14, 2);
            $table->string('cost_kind', 12)->default('purchase'); // purchase|leasing|service|mixed
            $table->string('financing', 12)->default('cash'); // cash|loan|leasing|subsidy|mixed
            $table->text('payment_plan')->nullable(); // Zahlungs-/Lieferplan
            $table->string('note', 1000)->nullable();
            $table->string('status', 12)->default('draft'); // draft|in_approval|approved|rejected|superseded
            $table->json('snapshot')->nullable(); // eingefrorener genehmigter Stand
            $table->foreignId('requested_by')->nullable()->constrained('users', indexName: 'invbr_requested_fk')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['investment_case_id', 'version'], 'invbr_case_version_unique');
        });

        Schema::create('investment_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'invl_org_fk')->cascadeOnDelete();
            $table->foreignId('investment_case_id')->constrained('investment_cases', indexName: 'invl_case_fk')->cascadeOnDelete();
            $table->string('linkable_type', 120);
            $table->unsignedBigInteger('linkable_id');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'invl_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id'], 'invl_linkable_idx');
            $table->unique(['investment_case_id', 'linkable_type', 'linkable_id'], 'invl_case_linkable_unique');
        });

        Schema::create('investment_actuals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'inva_org_fk')->cascadeOnDelete();
            $table->foreignId('investment_case_id')->constrained('investment_cases', indexName: 'inva_case_fk')->cascadeOnDelete();
            $table->string('source', 20)->default('manual'); // manual|incoming_invoice|purchase_order|asset|project
            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'inva_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('investment_deviations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'invdev_org_fk')->cascadeOnDelete();
            $table->foreignId('investment_case_id')->constrained('investment_cases', indexName: 'invdev_case_fk')->cascadeOnDelete();
            $table->string('kind', 15)->default('budget'); // budget|schedule|scope|cancellation
            $table->string('description', 1000);
            $table->decimal('amount_delta', 14, 2)->nullable();
            $table->string('status', 10)->default('open'); // open|approved|rejected
            $table->foreignId('decided_by')->nullable()->constrained('users', indexName: 'invdev_decided_fk')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'invdev_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('investment_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'invr_org_fk')->cascadeOnDelete();
            $table->foreignId('investment_case_id')->constrained('investment_cases', indexName: 'invr_case_fk')->cascadeOnDelete();
            $table->text('benefit_result')->nullable(); // tatsächlicher Nutzen
            $table->text('economics_result')->nullable(); // Wirtschaftlichkeit
            $table->text('lessons')->nullable();
            $table->text('follow_up')->nullable(); // Folgeinvestitionen/-aufgaben
            $table->foreignId('reviewed_by')->nullable()->constrained('users', indexName: 'invr_reviewed_fk')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('investment_case_id', 'invr_case_unique');
        });

        // Anschaffungsdaten am Asset (Recherche-Lücke für MVP-204/205).
        Schema::table('assets', function (Blueprint $table): void {
            $table->decimal('acquisition_cost', 14, 2)->nullable();
            $table->date('acquired_on')->nullable();
            $table->foreignId('acquired_from_supplier_id')->nullable()
                ->constrained('suppliers', indexName: 'assets_acq_supplier_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('acquired_from_supplier_id');
            $table->dropColumn(['acquisition_cost', 'acquired_on']);
        });
        Schema::dropIfExists('investment_reviews');
        Schema::dropIfExists('investment_deviations');
        Schema::dropIfExists('investment_actuals');
        Schema::dropIfExists('investment_links');
        Schema::dropIfExists('investment_budget_requests');
        Schema::dropIfExists('investment_options');
        Schema::dropIfExists('investment_cases');
        Schema::dropIfExists('cost_centers');
    }
};
