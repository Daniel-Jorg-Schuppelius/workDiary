<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->index('archived_at');
            $table->index('start_at');
        });

        Schema::table('on_call_shifts', function (Blueprint $table): void {
            $table->index('is_archived');
        });

        Schema::table('emergency_assignments', function (Blueprint $table): void {
            $table->index('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropIndex(['archived_at']);
            $table->dropIndex(['start_at']);
        });

        Schema::table('on_call_shifts', function (Blueprint $table): void {
            $table->dropIndex(['is_archived']);
        });

        Schema::table('emergency_assignments', function (Blueprint $table): void {
            $table->dropIndex(['is_archived']);
        });
    }
};
