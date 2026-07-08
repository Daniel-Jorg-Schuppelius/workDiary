<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_102100_create_service_queues_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Feature 065, P0 (MVP-150): Queues als Arbeitsvorrat des Helpdesks +
 * Backfill OHNE Verlust — jede Org mit vorhandenen Tickets bekommt eine
 * Default-Queue, alle Bestandstickets werden ihr zugeordnet (DoD 065).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('service_queues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'svq_org_fk')
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('purpose', 500)->nullable();
            $table->foreignId('team_id')->nullable()
                ->constrained('teams', indexName: 'svq_team_fk')
                ->nullOnDelete();
            $table->string('data_ownership', 12)->default('native'); // native|external (P8)
            $table->json('supported_kinds')->nullable();   // incident|service_request|question
            $table->json('business_hours')->nullable();    // P3: Geschäftszeiten-SLA
            $table->string('holiday_region', 10)->nullable();
            $table->foreignId('default_sla_contract_id')->nullable()
                ->constrained('sla_contracts', indexName: 'svq_sla_fk')
                ->nullOnDelete();
            $table->foreignId('email_connection_id')->nullable()
                ->constrained('email_connections', indexName: 'svq_mail_fk')
                ->nullOnDelete();
            $table->json('sender_identity')->nullable();   // Absender je Queue (P2)
            $table->string('visibility', 12)->default('internal');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'name'], 'svq_org_name_unique');
        });

        Schema::table('service_tickets', function (Blueprint $table): void {
            // NOT NULL erst logisch nach dem Backfill — Spalte bleibt
            // nullable, der Service erzwingt die Queue bei Neuanlage.
            $table->foreignId('queue_id')->nullable()->after('organization_id')
                ->constrained('service_queues', indexName: 'svt_queue_fk')
                ->nullOnDelete();
        });

        // Backfill: Default-Queue je Org mit Tickets, idempotent.
        $orgIds = DB::table('service_tickets')->distinct()->pluck('organization_id');
        foreach ($orgIds as $orgId) {
            $queueId = DB::table('service_queues')
                ->where('organization_id', $orgId)
                ->where('is_default', true)
                ->value('id');
            if ($queueId === null) {
                $queueId = DB::table('service_queues')->insertGetId([
                    'organization_id' => $orgId,
                    'name' => 'Allgemein',
                    'purpose' => 'Automatisch angelegte Standard-Queue (Migration Feature 065).',
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('service_tickets')
                ->where('organization_id', $orgId)
                ->whereNull('queue_id')
                ->update(['queue_id' => $queueId]);
        }
    }

    public function down(): void {
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('queue_id');
        });
        Schema::dropIfExists('service_queues');
    }
};
