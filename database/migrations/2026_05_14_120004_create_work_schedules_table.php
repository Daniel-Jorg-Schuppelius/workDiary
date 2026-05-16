<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_14_120004_create_work_schedules_table.php
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
        Schema::create('work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('weekly_minutes')->default(2400);
            $table->unsignedInteger('daily_target_minutes')->default(480);
            // JSON list of ISO weekday numbers (1=Mo..7=So)
            $table->json('working_days');
            $table->time('core_start')->nullable();
            $table->time('core_end')->nullable();
            $table->time('frame_start')->nullable();
            $table->time('frame_end')->nullable();
            $table->unsignedInteger('break_after_minutes')->default(360);
            $table->unsignedInteger('break_minutes')->default(30);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'valid_from']);
            $table->index(['user_id', 'valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
