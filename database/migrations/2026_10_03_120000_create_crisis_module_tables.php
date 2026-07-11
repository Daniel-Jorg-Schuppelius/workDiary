<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_03_120000_create_crisis_module_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 070 (Phase 21, MVP-211–222): Notfall-/Krisenmanagement —
 * Krisenakte mit Lagebild (versioniert, append-only), Krisenstab mit
 * Stellvertretung und Alarm-Quittierung, Entscheidungsprotokoll,
 * Maßnahmen, Kommunikationsplan (Entwurf/Freigabe/Aussendung getrennt),
 * BCM-Sicht (RTO/RPO/Workarounds), Übungen, Nachbereitung und
 * Fristen-Templates als Katalogdaten (Flexibilitätsplan D9).
 * Querschnitts-Vorleistung D7: Alarm-Quittierung + Ruhezeit-Ausnahme im
 * Notification-Framework.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('crisis_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cri_org_fk')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('category', 20)->default('it_outage'); // it_outage|security|privacy|safety|infrastructure|supply|other
            $table->string('severity', 10)->default('major'); // minor|major|critical
            $table->string('status', 15)->default('reported'); // prepared|reported|assessed|activated|in_progress|stabilized|recovery|all_clear|post_review|closed|discarded
            $table->string('trigger_source', 200)->nullable(); // Auslöser (frei/Systemname)
            $table->text('description')->nullable();
            $table->text('affected_summary')->nullable(); // Standorte/Services/Kunden/Assets
            $table->foreignId('responsible_user_id')->nullable()->constrained('users', indexName: 'cri_resp_fk')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('all_clear_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'cri_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'cri_org_status_idx');
        });

        Schema::create('crisis_case_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cril_org_fk')->cascadeOnDelete();
            $table->foreignId('crisis_case_id')->constrained('crisis_cases', indexName: 'cril_case_fk')->cascadeOnDelete();
            $table->string('linkable_type', 120);
            $table->unsignedBigInteger('linkable_id');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'cril_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id'], 'cril_linkable_idx');
            $table->unique(['crisis_case_id', 'linkable_type', 'linkable_id'], 'cril_case_linkable_unique');
        });

        Schema::create('crisis_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'crr_org_fk')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name'], 'crr_org_name_unique');
        });

        Schema::create('crisis_team_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cta_org_fk')->cascadeOnDelete();
            $table->foreignId('crisis_case_id')->constrained('crisis_cases', indexName: 'cta_case_fk')->cascadeOnDelete();
            $table->foreignId('crisis_role_id')->constrained('crisis_roles', indexName: 'cta_role_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', indexName: 'cta_user_fk')->cascadeOnDelete();
            $table->foreignId('deputy_user_id')->nullable()->constrained('users', indexName: 'cta_deputy_fk')->nullOnDelete();
            $table->string('contact_note', 300)->nullable(); // Erreichbarkeit
            $table->timestamp('alerted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('deputy_alerted_at')->nullable();
            $table->timestamps();

            $table->unique(['crisis_case_id', 'crisis_role_id', 'user_id'], 'cta_case_role_user_unique');
        });

        Schema::create('crisis_situation_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'csr_org_fk')->cascadeOnDelete();
            $table->foreignId('crisis_case_id')->constrained('crisis_cases', indexName: 'csr_case_fk')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('content'); // Lage/Bewertung
            $table->text('risks')->nullable(); // offene Risiken
            $table->text('communication_status')->nullable();
            $table->text('recovery_status')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'csr_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['crisis_case_id', 'version'], 'csr_case_version_unique');
        });

        Schema::create('crisis_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'crd_org_fk')->cascadeOnDelete();
            $table->foreignId('crisis_case_id')->constrained('crisis_cases', indexName: 'crd_case_fk')->cascadeOnDelete();
            $table->timestamp('decided_at');
            $table->string('decision', 1000);
            $table->string('rationale', 1000)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users', indexName: 'crd_decided_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crisis_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cra_org_fk')->cascadeOnDelete();
            $table->foreignId('crisis_case_id')->constrained('crisis_cases', indexName: 'cra_case_fk')->cascadeOnDelete();
            $table->string('title', 300);
            $table->string('description', 1000)->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users', indexName: 'cra_assignee_fk')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->string('priority', 10)->default('high'); // low|medium|high
            $table->string('status', 12)->default('open'); // open|in_progress|done|cancelled
            $table->foreignId('depends_on_id')->nullable()->constrained('crisis_actions', indexName: 'cra_depends_fk')->nullOnDelete();
            $table->string('evidence_note', 1000)->nullable(); // Nachweis
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crisis_communications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'crc_org_fk')->cascadeOnDelete();
            $table->foreignId('crisis_case_id')->constrained('crisis_cases', indexName: 'crc_case_fk')->cascadeOnDelete();
            $table->string('audience', 15); // internal|customers|suppliers|authorities|dpa|insurer|public
            $table->string('subject', 300);
            $table->text('body');
            $table->string('status', 10)->default('draft'); // draft|approved|sent
            $table->foreignId('approved_by')->nullable()->constrained('users', indexName: 'crc_approved_by_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('channel', 100)->nullable(); // Mail/Telefon/Portal/Presse …
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'crc_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crisis_continuity_impacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cci_org_fk')->cascadeOnDelete();
            $table->foreignId('crisis_case_id')->constrained('crisis_cases', indexName: 'cci_case_fk')->cascadeOnDelete();
            $table->string('process_name', 200); // kritischer Prozess/Service
            $table->unsignedSmallInteger('rto_hours')->nullable(); // Wiederanlaufziel
            $table->unsignedSmallInteger('rpo_hours')->nullable(); // max. Datenverlust
            $table->string('workaround', 1000)->nullable();
            $table->string('substitute_process', 1000)->nullable(); // manueller Ersatzprozess
            $table->string('status', 12)->default('down'); // down|degraded|workaround|restored
            $table->string('residual_note', 1000)->nullable(); // Restmaßnahmen
            $table->timestamps();
        });

        Schema::create('crisis_exercises', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cex_org_fk')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('scenario');
            $table->timestamp('exercised_at')->nullable();
            $table->text('participants')->nullable();
            $table->text('observations')->nullable();
            $table->text('deviations')->nullable();
            $table->string('effectiveness', 12)->nullable(); // effective|partly|ineffective
            $table->text('follow_up')->nullable();
            $table->foreignId('playbook_template_id')->nullable()->constrained('procedure_templates', indexName: 'cex_playbook_fk')->nullOnDelete();
            $table->date('next_due_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'cex_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crisis_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'crev_org_fk')->cascadeOnDelete();
            $table->foreignId('crisis_case_id')->constrained('crisis_cases', indexName: 'crev_case_fk')->cascadeOnDelete();
            $table->text('summary');
            $table->text('lessons')->nullable();
            $table->text('follow_up')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users', indexName: 'crev_reviewed_fk')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('crisis_case_id', 'crev_case_unique');
        });

        // Fristen-Templates als Katalogdaten (D9): org NULL = globaler
        // Default (Seeder), Org-Zeilen überschreiben je Kategorie.
        Schema::create('crisis_deadline_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations', indexName: 'cdt_org_fk')->cascadeOnDelete();
            $table->string('category', 20); // Krisen-Kategorie
            $table->string('label', 200); // z. B. „NIS2 Frühwarnung"
            $table->unsignedInteger('offset_hours')->nullable(); // null = unverzüglich
            $table->string('source', 300)->nullable(); // Rechtsgrundlage
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Querschnitts-Vorleistung D7: Alarm-Quittierung + Ruhezeit-Ausnahme.
        Schema::table('notification_dispatch_log', function (Blueprint $table): void {
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()
                ->constrained('users', indexName: 'ndl_ack_by_fk')
                ->nullOnDelete();
        });
        Schema::table('notification_rules', function (Blueprint $table): void {
            $table->boolean('override_quiet_hours')->default(false);
        });
    }

    public function down(): void {
        Schema::table('notification_rules', function (Blueprint $table): void {
            $table->dropColumn('override_quiet_hours');
        });
        Schema::table('notification_dispatch_log', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropColumn('acknowledged_at');
        });
        Schema::dropIfExists('crisis_deadline_templates');
        Schema::dropIfExists('crisis_reviews');
        Schema::dropIfExists('crisis_exercises');
        Schema::dropIfExists('crisis_continuity_impacts');
        Schema::dropIfExists('crisis_communications');
        Schema::dropIfExists('crisis_actions');
        Schema::dropIfExists('crisis_decisions');
        Schema::dropIfExists('crisis_situation_reports');
        Schema::dropIfExists('crisis_team_assignments');
        Schema::dropIfExists('crisis_roles');
        Schema::dropIfExists('crisis_case_links');
        Schema::dropIfExists('crisis_cases');
    }
};
