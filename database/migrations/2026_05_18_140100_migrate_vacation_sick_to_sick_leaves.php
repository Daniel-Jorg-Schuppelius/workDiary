<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_18_140100_migrate_vacation_sick_to_sick_leaves.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('vacations')->where('type', 'sick')->get();
        $now = now();
        foreach ($rows as $row) {
            DB::table('sick_leaves')->insert([
                'organization_id' => $row->organization_id ?? null,
                'user_id' => $row->user_id,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'kind' => 'initial',
                'follow_up_for_id' => null,
                'au_number' => null,
                'doctor_name' => null,
                'note' => $row->note,
                'kasse_notified_at' => null,
                'reported_at' => $row->decided_at ?? $row->created_at ?? $now,
                'recorded_by' => $row->decided_by ?? $row->user_id,
                'cancelled_at' => $row->status === 'cancelled' ? ($row->decided_at ?? $row->updated_at ?? $now) : null,
                'cancel_reason' => $row->status === 'rejected' ? $row->reject_reason : null,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ]);
        }

        DB::table('vacations')->where('type', 'sick')->delete();
    }

    public function down(): void
    {
        $rows = DB::table('sick_leaves')->get();
        $now = now();
        foreach ($rows as $row) {
            DB::table('vacations')->insert([
                'organization_id' => $row->organization_id ?? null,
                'user_id' => $row->user_id,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'type' => 'sick',
                'status' => $row->cancelled_at ? 'cancelled' : 'approved',
                'note' => $row->note,
                'reject_reason' => $row->cancel_reason,
                'decided_by' => $row->recorded_by,
                'decided_at' => $row->reported_at,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ]);
        }
    }
};
