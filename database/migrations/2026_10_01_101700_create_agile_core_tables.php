<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_101700_create_agile_core_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agiles Projektmanagement — Kern (Feature 064, MVP-139): Board je Projekt
 * (Kanban/Scrum), Spalten mit WIP-Limit und die Work-Item-Beistelltabelle
 * (1:1 zu Task — KEIN zweites Aufgabenmodell, nie Boardzustand am Task).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('agile_boards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'agb_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('project_id')
                ->constrained('projects', indexName: 'agb_project_fk')
                ->cascadeOnDelete();
            $table->string('method', 12)->default('kanban'); // kanban|scrum
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->json('dod_items')->nullable(); // geordnete Definition-of-Done-Liste
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()
                ->constrained('users', indexName: 'agb_created_by_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique('project_id', 'agb_project_unique'); // ein Board je Projekt (MVP)
        });

        Schema::create('agile_board_columns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'agbc_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('board_id')
                ->constrained('agile_boards', indexName: 'agbc_board_fk')
                ->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('category', 20); // open|in_progress|done (AgileColumnCategory)
            $table->string('report_role', 20)->nullable(); // working|waiting (Flow-Effizienz, MVP-147)
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('wip_limit')->nullable();
            $table->timestamps();

            $table->unique(['board_id', 'position'], 'agbc_board_pos');
        });

        Schema::create('agile_work_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'agwi_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('board_id')
                ->constrained('agile_boards', indexName: 'agwi_board_fk')
                ->cascadeOnDelete();
            $table->foreignId('task_id')
                ->constrained('tasks', indexName: 'agwi_task_fk')
                ->cascadeOnDelete();
            $table->string('item_type', 12)->default('task'); // epic|story|task|bug
            $table->foreignId('column_id')->nullable()
                ->constrained('agile_board_columns', indexName: 'agwi_column_fk')
                ->nullOnDelete(); // null = nur im Produkt-Backlog
            $table->unsignedInteger('backlog_rank'); // lückenbehaftet (1000er-Schritte)
            $table->unsignedSmallInteger('story_points')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason', 300)->nullable(); // Pflicht bei Blockierung (Service)
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique('task_id', 'agwi_task_unique'); // 1:1 zu Task
            $table->index(['board_id', 'backlog_rank'], 'agwi_board_rank_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('agile_work_items');
        Schema::dropIfExists('agile_board_columns');
        Schema::dropIfExists('agile_boards');
    }
};
