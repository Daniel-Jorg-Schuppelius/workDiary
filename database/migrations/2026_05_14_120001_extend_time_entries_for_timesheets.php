<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_14_120001_extend_time_entries_for_timesheets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('timesheet_id')->nullable()->after('project_id')
                ->constrained('timesheets')->nullOnDelete();
            $table->dateTime('started_at')->nullable()->after('date');
            $table->dateTime('ended_at')->nullable()->after('started_at');
            $table->unsignedInteger('break_minutes')->default(0)->after('ended_at');
            $table->string('kind', 20)->default('work')->after('break_minutes');

            $table->index('timesheet_id');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropForeign(['timesheet_id']);
            $table->dropIndex(['timesheet_id']);
            $table->dropIndex(['started_at']);
            $table->dropColumn(['timesheet_id', 'started_at', 'ended_at', 'break_minutes', 'kind']);
        });
    }
};
