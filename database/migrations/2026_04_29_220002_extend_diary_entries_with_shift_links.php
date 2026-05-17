<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_04_29_220002_extend_diary_entries_with_shift_links.php
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
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->foreignId('on_call_shift_id')
                ->nullable()
                ->after('user_id')
                ->constrained('on_call_shifts')
                ->nullOnDelete();

            $table->foreignId('emergency_assignment_id')
                ->nullable()
                ->after('on_call_shift_id')
                ->constrained('emergency_assignments')
                ->nullOnDelete();

            $table->boolean('is_archived')->default(false)->after('end_at');
            $table->dateTime('archived_at')->nullable()->after('is_archived');

            $table->index(['user_id', 'status', 'start_at']);
            $table->index('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('on_call_shift_id');
            $table->dropConstrainedForeignId('emergency_assignment_id');
            $table->dropIndex(['user_id', 'status', 'start_at']);
            $table->dropIndex(['is_archived']);
            $table->dropColumn(['is_archived', 'archived_at']);
        });
    }
};
