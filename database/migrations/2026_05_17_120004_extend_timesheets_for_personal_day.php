<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_120004_extend_timesheets_for_personal_day.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends timesheets to support a "personal day" mode that bundles all
 * TimeEntries of a single user/day across projects and administration.
 *
 *  - kind=project       : classic per-project sheet (existing behaviour)
 *  - kind=personal_day  : per-user daily sheet, project_id NULL allowed
 *
 * Adds snapshot columns for reconciliation against attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
        });

        Schema::table('timesheets', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->change();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();

            $table->string('kind', 20)->default('project')->after('user_id');
            $table->unsignedInteger('attendance_total_minutes')->default(0)
                ->after('totals_minutes');
            $table->unsignedInteger('entries_total_minutes')->default(0)
                ->after('attendance_total_minutes');
            $table->integer('untracked_minutes')->default(0)
                ->after('entries_total_minutes');

            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table): void {
            $table->dropIndex(['kind']);
            $table->dropColumn([
                'kind',
                'attendance_total_minutes',
                'entries_total_minutes',
                'untracked_minutes',
            ]);
        });

        Schema::table('timesheets', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
        });
        Schema::table('timesheets', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
