<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_03_120000_create_integration_outbox_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generische Integrations-Outbox (Feature 055, MVP-114): persistierte,
 * idempotente Übergabe lokaler Änderungen an externe Systeme — Muster der
 * `inventory_outbox` (Idempotenzschlüssel, Status-Enum, Registry, Job mit
 * Backoff, Kompensation statt Rollback) generalisiert. Die Inventory-Outbox
 * bleibt unangetastet. Kurze, explizite FK-/Index-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('integration_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'iob_org_fk')->cascadeOnDelete();
            $table->string('plugin_id', 64);
            $table->string('operation', 64);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload');
            $table->string('idempotency_key', 191);
            $table->string('status', 32)->default('pending'); // pending/processing/confirmed/failed/compensation_required
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 191)->nullable(); // gekürzte Fehlerklasse, nie Payload
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key'], 'iob_org_key_unique');
            $table->index(['plugin_id', 'status'], 'iob_plugin_status_idx');
            $table->index(['subject_type', 'subject_id'], 'iob_subject_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('integration_outbox');
    }
};
