<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_18_100002_migrate_service_orders_to_diary_entries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kopiert alle service_orders in diary_entries und verlinkt sie auf die
     * EntryType-Profile vom Slug "service" (pro Organisation). Anschließend
     * können die alten Tabellen/Klassen ohne Datenverlust entfernt werden.
     */
    public function up(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        // Mapping organization_id => entry_type_id (slug=service)
        $typeMap = DB::table('entry_types')
            ->where('slug', 'service')
            ->pluck('id', 'organization_id');

        $now = now();
        $rows = DB::table('service_orders')->orderBy('id')->cursor();

        foreach ($rows as $so) {
            $orgId = $so->organization_id;
            $entryTypeId = $typeMap[$orgId] ?? $typeMap[null] ?? null;

            $status = match ($so->status) {
                'planned' => 2,
                'assigned', 'in_progress' => 1,
                'done' => -1,
                'cancelled' => 3,
                default => 2,
            };

            // Inhalt zusammenführen aus title + description
            $content = trim(($so->title ?? '').(($so->description ?? '') !== '' ? "\n\n".$so->description : ''));
            if ($content === '') {
                $content = $so->title ?? '(ohne Titel)';
            }

            DB::table('diary_entries')->insert([
                'organization_id' => $orgId,
                'entry_type_id' => $entryTypeId,
                'user_id' => $so->assigned_user_id ?? $so->created_by ?? 0,
                'assigned_user_id' => $so->assigned_user_id,
                'project_id' => $so->project_id,
                'customer_id' => $so->customer_id,
                'title' => $so->title,
                'content' => $content,
                'status' => $status,
                'priority' => $so->priority,
                'scheduled_for' => $so->scheduled_for,
                'time_window_start' => $so->time_window_start,
                'time_window_end' => $so->time_window_end,
                'service_minutes' => $so->service_minutes,
                'address_line' => $so->address_line,
                'address_zip' => $so->address_zip,
                'address_city' => $so->address_city,
                'address_country' => $so->address_country,
                'address_lat' => $so->address_lat,
                'address_lng' => $so->address_lng,
                'tour_id' => $so->tour_id,
                'tour_position' => $so->tour_position,
                'notes' => $so->notes,
                'is_archived' => false,
                'created_at' => $so->created_at ?? $now,
                'updated_at' => $so->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        // No-op: irreversible Datenmigration. Wiederherstellung erfolgt durch DB-Backup.
    }
};
