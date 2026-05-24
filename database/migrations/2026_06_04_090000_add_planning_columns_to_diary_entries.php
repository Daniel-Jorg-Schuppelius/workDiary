<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_04_090000_add_planning_columns_to_diary_entries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('diary_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('diary_entries', 'planned_minutes')) {
                $table->unsignedInteger('planned_minutes')->nullable()->after('service_minutes');
            }

            if (! Schema::hasColumn('diary_entries', 'planned_at')) {
                $table->timestamp('planned_at')->nullable()->after('planned_minutes');
            }

            if (! Schema::hasColumn('diary_entries', 'planned_by_user_id')) {
                $table->foreignId('planned_by_user_id')->nullable()->after('planned_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void {
        Schema::table('diary_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('diary_entries', 'planned_by_user_id')) {
                $table->dropConstrainedForeignId('planned_by_user_id');
            }

            if (Schema::hasColumn('diary_entries', 'planned_at')) {
                $table->dropColumn('planned_at');
            }

            if (Schema::hasColumn('diary_entries', 'planned_minutes')) {
                $table->dropColumn('planned_minutes');
            }
        });
    }
};
