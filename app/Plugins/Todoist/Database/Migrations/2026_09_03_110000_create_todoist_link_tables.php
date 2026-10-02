<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_03_110000_create_todoist_link_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Todoist-Zuordnungen (Feature 055, MVP-112): explizite Admin-Zuordnung von
 * Todoist-Projekten auf WorkDiary-Projekte bzw. das globale Kanban sowie
 * optionale Abschnitts→Status-Zuordnungen. Benutzerzuordnungen laufen über
 * `external_references` (plugin todoist, external_type collaborator) — keine
 * eigene Tabelle. Kurze, explizite FK-/Index-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('todoist_project_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'tdl_org_fk')->cascadeOnDelete();
            $table->string('todoist_project_id');
            $table->string('todoist_project_name')->nullable(); // Anzeige-Snapshot
            $table->string('target_kind', 24); // project / global_kanban
            $table->foreignId('project_id')->nullable()->constrained('projects', indexName: 'tdl_proj_fk')->cascadeOnDelete();
            $table->string('sync_mode', 32)->default('todoist_to_workdiary'); // todoist_to_workdiary / workdiary_to_todoist / bidirectional
            $table->string('status', 16)->default('draft'); // draft / active / paused
            $table->timestamp('last_run_at')->nullable();
            $table->json('last_run_counters')->nullable(); // Zähler-Snapshot des letzten Laufs
            $table->timestamps();

            $table->unique(['organization_id', 'todoist_project_id'], 'tdl_unique');
        });

        Schema::create('todoist_section_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'tsl_org_fk')->cascadeOnDelete();
            $table->foreignId('todoist_project_link_id')->constrained('todoist_project_links', indexName: 'tsl_link_fk')->cascadeOnDelete();
            $table->string('todoist_section_id');
            $table->string('name')->nullable(); // Anzeige-Snapshot
            $table->string('task_status', 24); // open / in_progress
            $table->timestamps();

            $table->unique(['todoist_project_link_id', 'todoist_section_id'], 'tsl_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('todoist_section_links');
        Schema::dropIfExists('todoist_project_links');
    }
};
