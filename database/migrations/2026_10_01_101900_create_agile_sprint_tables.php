<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_101900_create_agile_sprint_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Feature 064, P4 (MVP-142): Sprints (max. EIN aktiver je Board — im
 * Service über lockForUpdate auf der Board-Zeile serialisiert) und die
 * Sprint-Zuordnung der Arbeitselemente. Snapshots (Commitment/Completion)
 * sind unveränderliche JSON-Ablagen für reproduzierbare Kennzahlen (P5).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('agile_sprints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'agsp_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('board_id')
                ->constrained('agile_boards', indexName: 'agsp_board_fk')
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('goal', 500)->nullable(); // Pflicht erst beim Start
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('planned'); // planned|active|completed|cancelled
            $table->json('commitment_snapshot')->nullable();  // beim Start, unveränderlich
            $table->json('completion_snapshot')->nullable();  // beim Abschluss, unveränderlich
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 300)->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users', indexName: 'agsp_created_by_fk')
                ->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['board_id', 'status'], 'agsp_board_status_idx');
        });

        Schema::create('agile_sprint_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'agsi_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('sprint_id')
                ->constrained('agile_sprints', indexName: 'agsi_sprint_fk')
                ->cascadeOnDelete();
            $table->foreignId('work_item_id')
                ->constrained('agile_work_items', indexName: 'agsi_item_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->boolean('added_after_start')->default(false); // Scope-Zugang
            $table->timestamps();

            $table->unique(['sprint_id', 'work_item_id'], 'agsi_sprint_item_unique');
        });

        Schema::table('agile_events', function (Blueprint $table): void {
            $table->index('sprint_id', 'age_sprint_idx');
        });
        // FK auf agile_events.sprint_id nur außerhalb von SQLite (Dev/Test):
        // ALTER TABLE … ADD CONSTRAINT wird dort nicht unterstützt.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('agile_events', function (Blueprint $table): void {
                $table->foreign('sprint_id', 'age_sprint_fk')
                    ->references('id')->on('agile_sprints')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void {
        Schema::table('agile_events', function (Blueprint $table): void {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('age_sprint_fk');
            }
            $table->dropIndex('age_sprint_idx');
        });
        Schema::dropIfExists('agile_sprint_items');
        Schema::dropIfExists('agile_sprints');
    }
};
