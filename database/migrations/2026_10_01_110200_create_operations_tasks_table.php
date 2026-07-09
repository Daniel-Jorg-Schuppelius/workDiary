<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_110200_create_operations_tasks_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 041, P0/P2 (MVP-058): Admin-Aufgabencenter für Betriebs-
 * aufgaben. Installationsweite Ereignisse (Backup, Updates, Scheduler)
 * tragen is_system=true und hängen an der Betreiber-Organisation
 * (Setting operations.system_org_id, Default: erste Organisation) —
 * so funktioniert die org-basierte Benachrichtigungs-Auflösung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('operations_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'opt_org_fk')
                ->cascadeOnDelete();
            $table->boolean('is_system')->default(false);
            $table->string('type', 40);
            $table->string('severity', 10); // info/warning/critical
            $table->string('status', 10);   // open/snoozed/delegated/ignored/done/resolved
            $table->string('dedupe_key', 150);
            $table->string('title_key', 150);
            $table->json('params')->nullable();
            $table->string('link_route', 100)->nullable();
            $table->json('link_params')->nullable();
            $table->string('assigned_role', 50)->nullable();
            $table->foreignId('assigned_user_id')->nullable()
                ->constrained('users', indexName: 'opt_assignee_fk')
                ->nullOnDelete();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('acted_by_user_id')->nullable()
                ->constrained('users', indexName: 'opt_actor_fk')
                ->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique('dedupe_key', 'opt_dedupe_unique');
            $table->index(['organization_id', 'status'], 'opt_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('operations_tasks');
    }
};
