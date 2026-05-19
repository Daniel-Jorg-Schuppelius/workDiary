<?php

/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_19_100000_add_diary_entry_id_to_time_entries.php
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
            $table->foreignId('diary_entry_id')->nullable()->after('task_id')
                ->constrained('diary_entries')->nullOnDelete();

            $table->index('diary_entry_id', 'te_diary_entry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropIndex('te_diary_entry_idx');
            $table->dropConstrainedForeignId('diary_entry_id');
        });
    }
};
