<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_14_140002_add_kimai_fields_to_tasks_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks act as Kimai "activities": time can be logged against them with
 * dedicated rate/budget overrides.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('priority');
            $table->decimal('internal_rate', 10, 2)->nullable()->after('hourly_rate');
            $table->unsignedInteger('time_budget')->default(0)->after('internal_rate');
            $table->decimal('budget', 12, 2)->default(0)->after('time_budget');
            $table->string('budget_type', 16)->nullable()->after('budget');
            $table->boolean('billable')->default(true)->after('budget_type');
            $table->boolean('is_global')->default(false)->after('billable');
            $table->string('color', 16)->nullable()->after('is_global');
            $table->timestamp('archived_at')->nullable()->after('due_date');

            $table->index('archived_at');
            $table->index('is_global');
        });
    }

    public function down(): void {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['archived_at']);
            $table->dropIndex(['is_global']);
            $table->dropColumn([
                'hourly_rate',
                'internal_rate',
                'time_budget',
                'budget',
                'budget_type',
                'billable',
                'is_global',
                'color',
                'archived_at',
            ]);
        });
    }
};
