<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_110100_create_scheduler_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 067, P2 (MVP-176/177): Datenbankbasierte Scheduler-Overrides
 * (aktiv/inaktiv, Kadenz) plus Laufzeit-Nachweise (Läufe + aggregierter
 * Zustand je Job). organization_id ist für künftige org-fähige Jobs
 * vorbereitet; im MVP schreiben nur System-Overrides (NULL).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('scheduled_job_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('job_key', 100);
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations', indexName: 'sjo_org_fk')
                ->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->json('cadence')->nullable(); // Cadence::toArray(); NULL = Default-Plan
            $table->foreignId('updated_by_user_id')->nullable()
                ->constrained('users', indexName: 'sjo_user_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['job_key', 'organization_id'], 'sjo_job_org_unique');
        });

        Schema::create('scheduled_job_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('job_key', 100);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20); // running/success/failed/skipped
            $table->unsignedInteger('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->timestamps();

            $table->index(['job_key', 'started_at'], 'sjr_job_started_idx');
        });

        Schema::create('scheduled_job_states', function (Blueprint $table): void {
            $table->id();
            $table->string('job_key', 100);
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('last_duration_ms')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->unique('job_key', 'sjs_job_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('scheduled_job_states');
        Schema::dropIfExists('scheduled_job_runs');
        Schema::dropIfExists('scheduled_job_overrides');
    }
};
