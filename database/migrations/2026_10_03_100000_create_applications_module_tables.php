<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_03_100000_create_applications_module_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 068 (Phase 19, MVP-183–198): Bewerbungs- und
 * Ausschreibungsprozesse als vorgelagerte Fallakten —
 * Ausschreibungsakten (Auftragsbewerbungen) mit Unterlagen-Checkliste
 * und versionierten Einreichungspaketen, Personalbewerbungen mit
 * verschlüsselter Kandidaten-PII (Zugriffstrennung, Löschkonzept),
 * gemeinsames Vertragsverhandlungsmodell (Morph auf beide Akten) und
 * Mitarbeiter-Entwurf als kontrollierte Onboarding-Übergabe (D4).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('application_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'aop_org_fk')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('kind', 20)->default('tender'); // inquiry|tender|participation|framework|direct|recurring
            $table->string('source', 200)->nullable(); // Portal/Medium/Empfehlung
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'aop_customer_fk')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects', indexName: 'aop_project_fk')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes', indexName: 'aop_quote_fk')->nullOnDelete();
            $table->foreignId('bill_of_quantity_id')->nullable()->constrained('bill_of_quantities', indexName: 'aop_boq_fk')->nullOnDelete();
            $table->string('status', 20)->default('captured'); // captured|screened|in_progress|question|submitted|post_submission|won|lost|withdrawn|archived
            $table->date('question_deadline')->nullable();
            $table->date('submission_deadline')->nullable();
            $table->date('decision_expected_on')->nullable();
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->unsignedTinyInteger('probability')->nullable(); // 0–100 %
            $table->text('risk_note')->nullable();
            $table->string('go_decision', 10)->default('pending'); // pending|go|no_go
            $table->foreignId('go_decided_by')->nullable()->constrained('users', indexName: 'aop_go_by_fk')->nullOnDelete();
            $table->timestamp('go_decided_at')->nullable();
            $table->string('go_note', 500)->nullable();
            $table->string('loss_reason', 500)->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users', indexName: 'aop_resp_fk')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'aop_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'aop_org_status_idx');
            $table->index(['organization_id', 'submission_deadline'], 'aop_org_deadline_idx');
        });

        Schema::create('application_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'aor_org_fk')->cascadeOnDelete();
            $table->foreignId('application_opportunity_id')->constrained('application_opportunities', indexName: 'aor_opp_fk')->cascadeOnDelete();
            $table->string('label', 300);
            $table->string('kind', 20)->default('document'); // document|proof|question|task
            $table->boolean('required')->default(true);
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('open'); // open|in_progress|done|not_applicable
            $table->foreignId('document_id')->nullable()->constrained('documents', indexName: 'aor_document_fk')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('application_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'aos_org_fk')->cascadeOnDelete();
            $table->foreignId('application_opportunity_id')->constrained('application_opportunities', indexName: 'aos_opp_fk')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('channel', 20)->default('portal'); // portal|email|paper|other
            $table->json('snapshot'); // eingefrorener Stand (Titel/Wert/Anforderungen/Dokumente)
            $table->char('sha256', 64);
            $table->string('note', 500)->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users', indexName: 'aos_submitted_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['application_opportunity_id', 'version'], 'aos_opp_version_unique');
        });

        Schema::create('job_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'jrq_org_fk')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('department', 120)->nullable();
            $table->text('profile')->nullable(); // Stellenprofil/Anforderungen
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->string('employment_type', 20)->default('full_time'); // full_time|part_time|apprentice|freelance
            $table->string('budget_note', 500)->nullable();
            $table->string('status', 20)->default('draft'); // draft|open|on_hold|filled|closed
            $table->foreignId('responsible_user_id')->nullable()->constrained('users', indexName: 'jrq_resp_fk')->nullOnDelete();
            $table->date('target_start_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'jrq_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'jrq_org_status_idx');
        });

        Schema::create('job_postings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'jpo_org_fk')->cascadeOnDelete();
            $table->foreignId('job_requisition_id')->constrained('job_requisitions', indexName: 'jpo_req_fk')->cascadeOnDelete();
            $table->string('channel', 20)->default('website'); // website|portal|agency|social|print|referral|other
            $table->string('reference', 200)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 20)->default('draft'); // draft|published|expired|closed
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'jap_org_fk')->cascadeOnDelete();
            $table->foreignId('job_requisition_id')->nullable()->constrained('job_requisitions', indexName: 'jap_req_fk')->nullOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained('job_postings', indexName: 'jap_posting_fk')->nullOnDelete();
            // Kandidaten-PII verschlüsselt at rest (Feature 016-Konvention);
            // email_hash als deterministischer Lookup für Dublettenprüfung.
            $table->text('candidate_name')->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->char('email_hash', 64)->nullable();
            $table->string('source', 20)->default('other'); // website|portal|agency|social|print|referral|other
            $table->string('status', 20)->default('received'); // received|screened|interview_planned|interviewed|task_open|offer|accepted|rejected|withdrawn|talent_pool|deleted
            $table->timestamp('received_at')->nullable();
            $table->timestamp('consent_talent_pool_at')->nullable();
            $table->date('consent_expires_on')->nullable();
            $table->date('retention_until')->nullable(); // Löschvormerkung (AGG-/Klagefrist)
            $table->text('notes')->nullable(); // verschlüsselt (interne Notizen)
            $table->foreignId('responsible_user_id')->nullable()->constrained('users', indexName: 'jap_resp_fk')->nullOnDelete();
            $table->timestamp('anonymized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'jap_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'jap_org_status_idx');
            $table->index(['organization_id', 'email_hash'], 'jap_org_email_idx');
            $table->index(['organization_id', 'retention_until'], 'jap_org_retention_idx');
        });

        Schema::create('job_application_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'jad_org_fk')->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained('job_applications', indexName: 'jad_app_fk')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents', indexName: 'jad_document_fk')->cascadeOnDelete();
            $table->string('label', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('job_application_interviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'jai_org_fk')->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained('job_applications', indexName: 'jai_app_fk')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('mode', 12)->default('onsite'); // onsite|remote|phone
            $table->foreignId('interviewer_id')->nullable()->constrained('users', indexName: 'jai_interviewer_fk')->nullOnDelete();
            $table->string('status', 12)->default('planned'); // planned|done|cancelled
            $table->text('notes')->nullable(); // verschlüsselt
            $table->unsignedTinyInteger('rating')->nullable(); // 1–5
            $table->timestamps();
        });

        Schema::create('job_application_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'jre_org_fk')->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained('job_applications', indexName: 'jre_app_fk')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users', indexName: 'jre_reviewer_fk')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('comment')->nullable(); // verschlüsselt
            $table->timestamps();
        });

        Schema::create('application_contract_negotiations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'acn_org_fk')->cascadeOnDelete();
            // Morph auf application_opportunities ODER job_applications (MVP-195).
            $table->string('negotiable_type', 120);
            $table->unsignedBigInteger('negotiable_id');
            $table->string('title', 200);
            $table->string('status', 20)->default('draft'); // draft|in_review|counter|approved|concluded|declined
            $table->date('due_on')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users', indexName: 'acn_resp_fk')->nullOnDelete();
            $table->string('decision', 12)->nullable(); // concluded|declined
            $table->foreignId('decided_by')->nullable()->constrained('users', indexName: 'acn_decided_by_fk')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acn_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['negotiable_type', 'negotiable_id'], 'acn_negotiable_idx');
        });

        Schema::create('application_contract_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'acv_org_fk')->cascadeOnDelete();
            $table->foreignId('negotiation_id')->constrained('application_contract_negotiations', indexName: 'acv_neg_fk')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('kind', 12)->default('draft'); // draft|counter|final
            $table->text('summary')->nullable();
            // Konditionen (Personal: Gehalt/Start/Arbeitszeit/Befristung/Probezeit;
            // Auftrag: Abweichungen zu Angebot/LV/Bedingungen) — besonders
            // zugriffsbeschränkt über die recruiting.*-/tender.*-Rechte.
            $table->text('conditions')->nullable(); // verschlüsselt (json-String)
            $table->foreignId('document_id')->nullable()->constrained('documents', indexName: 'acv_document_fk')->nullOnDelete();
            $table->char('sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acv_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['negotiation_id', 'version'], 'acv_neg_version_unique');
        });

        Schema::create('application_contract_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'acr_org_fk')->cascadeOnDelete();
            $table->foreignId('negotiation_id')->constrained('application_contract_negotiations', indexName: 'acr_neg_fk')->cascadeOnDelete();
            $table->string('label', 500);
            $table->string('severity', 12)->default('important'); // info|important|blocker
            $table->string('status', 12)->default('open'); // open|resolved|accepted
            $table->string('note', 1000)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users', indexName: 'acr_resolved_by_fk')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'edr_org_fk')->cascadeOnDelete();
            $table->foreignId('job_application_id')->nullable()->constrained('job_applications', indexName: 'edr_app_fk')->nullOnDelete();
            $table->string('name', 200);
            $table->string('email', 200)->nullable();
            $table->date('planned_start_on')->nullable();
            $table->json('qualifications')->nullable();
            $table->json('checklist')->nullable(); // Onboarding-Checkliste [{label, done}]
            $table->string('note', 1000)->nullable();
            $table->string('status', 12)->default('draft'); // draft|invited|discarded
            $table->foreignId('invited_user_id')->nullable()->constrained('users', indexName: 'edr_invited_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'edr_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('employee_drafts');
        Schema::dropIfExists('application_contract_reviews');
        Schema::dropIfExists('application_contract_versions');
        Schema::dropIfExists('application_contract_negotiations');
        Schema::dropIfExists('job_application_reviews');
        Schema::dropIfExists('job_application_interviews');
        Schema::dropIfExists('job_application_documents');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('job_requisitions');
        Schema::dropIfExists('application_submissions');
        Schema::dropIfExists('application_requirements');
        Schema::dropIfExists('application_opportunities');
    }
};
