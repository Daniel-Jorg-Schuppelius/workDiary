<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_21_110000_create_tour_stops_proposals_and_events.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Phase 146, MVP-1174/1176: Stopps als eigene Zeilen (geplante Ankunft,
 * Wartezeit, Ist), Tourvorschläge als Datensatz, Tour-Journal; Tour mit
 * Startzeit und Betriebsstätte als Startanker. Bestand: je Auftrag mit
 * `tour_id` eine Stoppzeile mit seiner Position.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('tours', function (Blueprint $table): void {
            $table->dateTime('planned_start_at')->nullable()->after('tour_date');
            $table->foreignId('start_site_id')->nullable()->after('planned_start_at')
                ->constrained('sites', indexName: 'tours_start_site_fk')->nullOnDelete();
        });

        Schema::create('tour_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'tour_stops_org_fk')->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained('tours', indexName: 'tour_stops_tour_fk')->cascadeOnDelete();
            $table->foreignId('diary_entry_id')->constrained('diary_entries', indexName: 'tour_stops_entry_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->dateTime('planned_arrival_at')->nullable();
            $table->dateTime('planned_departure_at')->nullable();
            $table->unsignedSmallInteger('waiting_minutes')->default(0);
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('departed_at')->nullable();
            $table->string('actual_source', 16)->nullable();
            $table->timestamps();

            $table->unique(['tour_id', 'diary_entry_id'], 'tour_stops_tour_entry_unique');
            $table->index(['tour_id', 'position'], 'tour_stops_tour_position_idx');
        });

        Schema::create('tour_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'tour_proposals_org_fk')->cascadeOnDelete();
            $table->foreignId('tour_id')->nullable()->constrained('tours', indexName: 'tour_proposals_tour_fk')->cascadeOnDelete();
            $table->date('proposal_date');
            $table->string('solver', 32)->nullable();
            $table->boolean('is_estimate')->default(false);
            $table->json('problem')->nullable();
            $table->json('plan')->nullable();
            $table->json('metrics')->nullable();
            $table->string('status', 16)->default('computing');
            $table->text('failure')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users', indexName: 'tour_proposals_decided_by_fk')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'tour_proposals_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'proposal_date'], 'tour_proposals_org_date_idx');
            $table->index(['tour_id', 'status'], 'tour_proposals_tour_status_idx');
        });

        Schema::create('tour_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'tour_events_org_fk')->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained('tours', indexName: 'tour_events_tour_fk')->cascadeOnDelete();
            $table->string('event', 64);
            $table->foreignId('actor_user_id')->nullable()->constrained('users', indexName: 'tour_events_actor_fk')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['tour_id', 'occurred_at'], 'tour_events_tour_time_idx');
        });

        // Bestand: Stoppzeilen aus den Aufträgen mit Tour.
        $rows = DB::table('diary_entries')
            ->whereNotNull('tour_id')
            ->orderBy('tour_id')
            ->orderByRaw('tour_position IS NULL')
            ->orderBy('tour_position')
            ->orderBy('id')
            ->get(['id', 'tour_id', 'organization_id', 'tour_position']);
        $position = [];
        $now = now();
        foreach ($rows as $row) {
            $position[$row->tour_id] = ($position[$row->tour_id] ?? 0) + 1;
            DB::table('tour_stops')->insert([
                'organization_id' => $row->organization_id,
                'tour_id' => $row->tour_id,
                'diary_entry_id' => $row->id,
                'position' => $position[$row->tour_id],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void {
        Schema::dropIfExists('tour_events');
        Schema::dropIfExists('tour_proposals');
        Schema::dropIfExists('tour_stops');
        Schema::table('tours', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('start_site_id');
            $table->dropColumn('planned_start_at');
        });
    }
};
