<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_101800_create_agile_backlog_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 064, P2 (MVP-140): Akzeptanzkriterien je Arbeitselement und die
 * append-only Prozesshistorie (agile_events — fachliche Änderung + Event
 * laufen in derselben Transaktion, Service-Schicht, nie Controller).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('agile_acceptance_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'agac_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('work_item_id')
                ->constrained('agile_work_items', indexName: 'agac_item_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('text', 500);
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('checked_by')->nullable()
                ->constrained('users', indexName: 'agac_checked_by_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['work_item_id', 'position'], 'agac_item_pos_idx');
        });

        Schema::create('agile_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'age_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('board_id')
                ->constrained('agile_boards', indexName: 'age_board_fk')
                ->cascadeOnDelete();
            $table->foreignId('work_item_id')->nullable()
                ->constrained('agile_work_items', indexName: 'age_item_fk')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('sprint_id')->nullable(); // FK folgt in P4 (agile_sprints)
            $table->string('event', 40); // fester Katalog, getestet
            $table->foreignId('actor_user_id')->nullable()
                ->constrained('users', indexName: 'age_actor_fk')
                ->nullOnDelete();
            $table->json('payload')->nullable(); // Vorher/Nachher
            $table->timestamp('created_at'); // append-only, KEIN updated_at

            $table->index(['board_id', 'created_at'], 'age_board_time_idx');
            $table->index(['work_item_id', 'created_at'], 'age_item_time_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('agile_events');
        Schema::dropIfExists('agile_acceptance_criteria');
    }
};
