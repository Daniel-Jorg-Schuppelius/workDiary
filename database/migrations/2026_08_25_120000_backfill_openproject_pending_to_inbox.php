<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_25_120000_backfill_openproject_pending_to_inbox.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Models\TimeEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Übernimmt offene OpenProject-Pending-Einträge in die universelle Zuordnungs-
 * Inbox (MVP-103, Phase 2b), gruppiert nach Projekt. Idempotent über den
 * dedupe_key. openproject_pending_entries bleibt vorerst bestehen (Drop später).
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('integration_inbox_items') || ! Schema::hasTable('openproject_pending_entries')) {
            return;
        }

        $targetType = (new TimeEntry)->getMorphClass();

        DB::table('openproject_pending_entries')->where('status', 'open')->orderBy('id')->each(function (object $row) use ($targetType): void {
            $projectExternalId = trim((string) ($row->project_external_id ?? ''));
            $project = trim((string) ($row->project_name ?? ''));
            $dedupeKey = 'entry:' . $row->entry_key;

            $exists = DB::table('integration_inbox_items')
                ->where('organization_id', $row->organization_id)
                ->where('plugin_id', 'openproject')
                ->where('dedupe_key', $dedupeKey)
                ->exists();
            if ($exists) {
                return;
            }

            $spentOn = $row->spent_on !== null ? Carbon::parse($row->spent_on) : null;

            DB::table('integration_inbox_items')->insert([
                'organization_id' => $row->organization_id,
                'plugin_id' => 'openproject',
                'source' => 'api',
                'target_type' => $targetType,
                'external_type' => 'entry',
                'external_id' => $row->entry_key,
                'dedupe_key' => $dedupeKey,
                'group_key' => $projectExternalId !== '' ? 'project:' . $projectExternalId : 'op:none',
                'case_type' => 'unmatched',
                'status' => 'open',
                'remote_snapshot' => json_encode([
                    'entry_key' => $row->entry_key,
                    'project_external_id' => $row->project_external_id,
                    'project_name' => $row->project_name,
                    'work_package_external_id' => $row->work_package_external_id,
                    'work_package_subject' => $row->work_package_subject,
                    'description' => $row->description,
                    'spent_on' => $spentOn?->toDateString(),
                    'minutes' => (int) $row->minutes,
                    'user_external_id' => $row->user_external_id,
                    'user_name' => $row->user_name,
                ], JSON_UNESCAPED_UNICODE),
                'display_title' => $project !== '' ? $project : '(ohne Projekt)',
                'display_subtitle' => $row->work_package_subject,
                'occurred_at' => $spentOn,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void {
        // Backfill ist nicht reversibel (Quelle bleibt erhalten).
    }
};
