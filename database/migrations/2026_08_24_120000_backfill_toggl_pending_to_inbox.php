<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_24_120000_backfill_toggl_pending_to_inbox.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Models\TimeEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Übernimmt offene Toggl-Pending-Einträge in die universelle Zuordnungs-Inbox
 * (MVP-103, Phase 2b), gruppiert nach (client, project). Idempotent über den
 * dedupe_key. Die alte Tabelle toggl_pending_entries bleibt vorerst bestehen
 * (Drop in späterer Migration nach Stabilisierung).
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('integration_inbox_items') || ! Schema::hasTable('toggl_pending_entries')) {
            return;
        }

        $targetType = (new TimeEntry)->getMorphClass();

        DB::table('toggl_pending_entries')->where('status', 'open')->orderBy('id')->each(function (object $row) use ($targetType): void {
            $client = trim((string) ($row->client_name ?? ''));
            $project = trim((string) ($row->project_name ?? ''));
            $groupKey = mb_strtolower($client . '|' . $project);
            $dedupeKey = 'entry:' . $row->entry_key;

            $exists = DB::table('integration_inbox_items')
                ->where('organization_id', $row->organization_id)
                ->where('plugin_id', 'toggl')
                ->where('dedupe_key', $dedupeKey)
                ->exists();
            if ($exists) {
                return;
            }

            $started = $row->started_at !== null ? Carbon::parse($row->started_at) : null;
            $ended = $row->ended_at !== null ? Carbon::parse($row->ended_at) : null;

            DB::table('integration_inbox_items')->insert([
                'organization_id' => $row->organization_id,
                'plugin_id' => 'toggl',
                'source' => $row->source,
                'target_type' => $targetType,
                'external_type' => 'entry',
                'external_id' => $row->entry_key,
                'dedupe_key' => $dedupeKey,
                'group_key' => $groupKey,
                'case_type' => 'unmatched',
                'status' => 'open',
                'remote_snapshot' => json_encode([
                    'source' => $row->source,
                    'entry_key' => $row->entry_key,
                    'client_name' => $row->client_name,
                    'project_name' => $row->project_name,
                    'description' => $row->description,
                    'started_at' => $started?->toIso8601String(),
                    'ended_at' => $ended?->toIso8601String(),
                    'billable' => (bool) $row->billable,
                    'user_email' => $row->user_email,
                ], JSON_UNESCAPED_UNICODE),
                'display_title' => $project !== '' ? $project : '(ohne Projekt)',
                'display_subtitle' => $client !== '' ? $client : null,
                'occurred_at' => $started,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void {
        // Backfill ist nicht reversibel (Quelle bleibt erhalten).
    }
};
