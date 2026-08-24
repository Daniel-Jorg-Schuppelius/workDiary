<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Vollscan 2026-08-23, F12: _id-Spalten, deren Zieltabellen inzwischen
 * existieren („kein FK bis Feature 091/087" ist Geschichte). Verwaiste
 * Referenzen werden vorher genullt — sie zeigten ins Leere. Nur MySQL.
 */
return new class extends Migration {
    private const FKS = [
        ['appointment_requests', 'lead_id', 'leads', 'appt_req_lead_fk'],
        ['appointment_requests', 'bookable_service_id', 'bookable_services', 'appt_req_service_fk'],
        ['procedure_deviations', 'open_issue_id', 'open_issues', 'procdev_open_issue_fk'],
        ['procedure_deviations', 'follow_up_diary_entry_id', 'diary_entries', 'procdev_followup_entry_fk'],
    ];

    public function up(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }
        foreach (self::FKS as [$table, $column, $target, $name]) {
            DB::table($table)
                ->whereNotNull($column)
                ->whereNotIn($column, DB::table($target)->select('id'))
                ->update([$column => null]);
            Schema::table($table, function (Blueprint $blueprint) use ($column, $target, $name): void {
                $blueprint->foreign($column, $name)->references('id')->on($target)->nullOnDelete();
            });
        }
    }

    public function down(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }
        foreach (self::FKS as [$table, $column, $target, $name]) {
            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropForeign($name);
            });
        }
    }
};
