<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_102800_create_changes_and_approvals_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 065, P7 (MVP-157): EINE Genehmigungsmechanik — generische
 * `approvals` mit approvable-Morph (ServiceRequest UND Change) — plus
 * Change Management (Typen standard/normal/emergency, versionierte
 * Standard-Change-Vorlagen, Outcome + PIR).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'apv_org_fk')
                ->cascadeOnDelete();
            $table->morphs('approvable', 'apv_subject_idx'); // ServiceRequest|Change
            $table->unsignedSmallInteger('step');
            $table->json('approver_rule'); // {type: role|user, value}
            $table->foreignId('decided_by')->nullable()
                ->constrained('users', indexName: 'apv_decided_by_fk')
                ->nullOnDelete();
            $table->string('decision', 12)->nullable(); // approved|rejected|question|delegated
            $table->string('reason', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('change_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'cht_org_fk')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('implementation_plan')->nullable();
            $table->text('test_plan')->nullable();
            $table->text('rollback_plan')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('approved')->default(false); // Standard-Changes = freigegebene Vorlagen
            $table->timestamps();
        });

        Schema::create('changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'chg_org_fk')
                ->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('change_type', 12)->default('normal'); // standard|normal|emergency
            $table->text('reason')->nullable();
            $table->text('scope')->nullable();
            $table->unsignedTinyInteger('risk')->nullable();    // 1–3
            $table->unsignedTinyInteger('impact')->nullable();  // 1–3
            $table->unsignedTinyInteger('urgency')->nullable(); // 1–3
            $table->timestamp('window_from')->nullable();
            $table->timestamp('window_to')->nullable();
            $table->text('implementation_plan')->nullable();
            $table->text('test_plan')->nullable();
            $table->text('rollback_plan')->nullable();
            $table->foreignId('change_template_id')->nullable()
                ->constrained('change_templates', indexName: 'chg_template_fk')
                ->nullOnDelete();
            $table->json('template_snapshot')->nullable(); // Vorlagenstand eingefroren
            $table->string('status', 20)->default('draft'); // draft|pending_approval|approved|implementing|done|cancelled
            $table->string('outcome', 30)->nullable(); // successful|successful_with_issues|failed|rolled_back|cancelled
            $table->text('pir_notes')->nullable();
            $table->timestamp('pir_done_at')->nullable();
            $table->foreignId('problem_id')->nullable()
                ->constrained('problems', indexName: 'chg_problem_fk')
                ->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users', indexName: 'chg_created_by_fk')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('change_ticket', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('change_id')
                ->constrained('changes', indexName: 'chtk_change_fk')
                ->cascadeOnDelete();
            $table->foreignId('service_ticket_id')
                ->constrained('service_tickets', indexName: 'chtk_ticket_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['change_id', 'service_ticket_id'], 'chtk_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('change_ticket');
        Schema::dropIfExists('changes');
        Schema::dropIfExists('change_templates');
        Schema::dropIfExists('approvals');
    }
};
