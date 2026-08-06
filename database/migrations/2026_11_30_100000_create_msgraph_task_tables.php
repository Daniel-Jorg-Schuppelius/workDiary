<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_30_100000_create_msgraph_task_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Microsoft-To-Do-Sync (Feature 102, Schnitt E — TaskSync, Todoist-Muster):
 * sechster Grant (`Tasks.ReadWrite`), eine OAuth-Verbindung je Org plus
 * explizite Listen-Zuordnungen (To-Do-Liste ↔ WorkDiary-Projekt/globales
 * Kanban) mit Sync-Richtung — nur ausdrücklich zugeordnete Listen werden
 * synchronisiert, Konflikte laufen über die Integrations-Inbox
 * (nie Last-write-wins).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('msgraph_task_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('msgtc_org_unique')->constrained('organizations', indexName: 'msgtc_org_fk')->cascadeOnDelete();
            $table->text('access_token')->nullable();   // encrypted-Cast
            $table->text('refresh_token')->nullable();  // encrypted-Cast
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes')->nullable();
            $table->string('account_label')->nullable();
            $table->string('status', 16)->default('active'); // active / disconnected
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'msgtc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'msgtc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('msgraph_task_list_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'msgtl_org_fk')->cascadeOnDelete();
            $table->string('todo_list_id', 512);             // Graph-Listen-IDs sind lang (Base64)
            $table->string('todo_list_name')->nullable();
            $table->string('target_kind', 32)->default('project'); // project / global_kanban
            $table->foreignId('project_id')->nullable()->constrained('projects', indexName: 'msgtl_project_fk')->cascadeOnDelete();
            $table->string('sync_mode', 32)->default('bidirectional'); // todo_to_workdiary / workdiary_to_todo / bidirectional
            $table->string('status', 16)->default('active'); // active / paused
            $table->timestamp('last_run_at')->nullable();
            $table->json('last_run_counters')->nullable();
            $table->timestamps();

            // Eine Liste je Org nur einmal zuordnen (keine konkurrierenden Läufe).
            $table->unique(['organization_id', 'todo_list_id'], 'msgtl_org_list_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('msgraph_task_list_links');
        Schema::dropIfExists('msgraph_task_connections');
    }
};
