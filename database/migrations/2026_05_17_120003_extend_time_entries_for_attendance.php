<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_120003_extend_time_entries_for_attendance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allows TimeEntries to exist without a project (administration, training,
 * meetings, ...) and to be linked to an attendance session and/or travel log.
 *
 *  - activity_type: project | admin | training | meeting | internal |
 *                   travel | break | absence | standby | other
 *  - When activity_type=project, project_id is required (enforced in model).
 *  - activity_category_id references the catalog entry for non-project work.
 */
return new class extends Migration {
    public function up(): void {
        // Drop the project_id FK so we can make it nullable.
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
        });

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->change();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();

            $table->string('activity_type', 32)->default('project')->after('kind');
            $table->foreignId('activity_category_id')->nullable()->after('activity_type')
                ->constrained('activity_categories')->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()->after('activity_category_id')
                ->constrained('attendances')->nullOnDelete();
            $table->foreignId('travel_log_id')->nullable()->after('attendance_id')
                ->constrained('travel_logs')->nullOnDelete();

            $table->index('activity_type');
            $table->index('activity_category_id');
            $table->index('attendance_id');
            $table->index('travel_log_id');
        });
    }

    public function down(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropForeign(['travel_log_id']);
            $table->dropForeign(['attendance_id']);
            $table->dropForeign(['activity_category_id']);
            $table->dropIndex(['travel_log_id']);
            $table->dropIndex(['attendance_id']);
            $table->dropIndex(['activity_category_id']);
            $table->dropIndex(['activity_type']);
            $table->dropColumn([
                'travel_log_id',
                'attendance_id',
                'activity_category_id',
                'activity_type',
            ]);
        });

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
        });
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
