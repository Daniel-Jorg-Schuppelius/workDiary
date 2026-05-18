<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_18_100001_extend_diary_entries_with_order_fields.php
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
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->foreignId('entry_type_id')->nullable()->after('organization_id')
                ->constrained('entry_types')->nullOnDelete();

            $table->foreignId('customer_id')->nullable()->after('project_id')
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();

            $table->string('title', 200)->nullable()->after('assigned_user_id');

            $table->string('address_line')->nullable()->after('title');
            $table->string('address_zip', 16)->nullable()->after('address_line');
            $table->string('address_city', 120)->nullable()->after('address_zip');
            $table->string('address_country', 2)->nullable()->after('address_city');
            $table->decimal('address_lat', 10, 7)->nullable()->after('address_country');
            $table->decimal('address_lng', 10, 7)->nullable()->after('address_lat');

            $table->date('scheduled_for')->nullable()->after('address_lng');
            $table->time('time_window_start')->nullable()->after('scheduled_for');
            $table->time('time_window_end')->nullable()->after('time_window_start');
            $table->unsignedSmallInteger('service_minutes')->nullable()->after('time_window_end');

            $table->string('priority', 16)->nullable()->after('service_minutes');

            $table->foreignId('tour_id')->nullable()->after('priority')
                ->constrained('tours')->nullOnDelete();
            $table->unsignedSmallInteger('tour_position')->nullable()->after('tour_id');

            $table->text('notes')->nullable()->after('tour_position');

            $table->index(['organization_id', 'scheduled_for'], 'de_org_sched_idx');
            $table->index(['assigned_user_id', 'scheduled_for'], 'de_assigned_sched_idx');
            $table->index(['tour_id', 'tour_position'], 'de_tour_pos_idx');
            $table->index('entry_type_id', 'de_entry_type_idx');
            $table->index('scheduled_for', 'de_scheduled_for_idx');
        });
    }

    public function down(): void
    {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropIndex('de_org_sched_idx');
            $table->dropIndex('de_assigned_sched_idx');
            $table->dropIndex('de_tour_pos_idx');
            $table->dropIndex('de_entry_type_idx');
            $table->dropIndex('de_scheduled_for_idx');

            $table->dropConstrainedForeignId('entry_type_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropConstrainedForeignId('tour_id');

            $table->dropColumn([
                'title',
                'address_line',
                'address_zip',
                'address_city',
                'address_country',
                'address_lat',
                'address_lng',
                'scheduled_for',
                'time_window_start',
                'time_window_end',
                'service_minutes',
                'priority',
                'tour_position',
                'notes',
            ]);
        });
    }
};
