<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> Tables that receive organization_id */
    private array $tables = [
        'users',
        'diary_entries',
        'on_call_shifts',
        'emergency_assignments',
        'scheduled_shifts',
        'shift_types',
        'holidays',
        'vacations',
        'projects',
        'tags',
        'audit_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->nullOnDelete();

                $blueprint->index('organization_id', "idx_{$table}_org");
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign(['organization_id']);
                $blueprint->dropIndex("idx_{$table}_org");
                $blueprint->dropColumn('organization_id');
            });
        }
    }
};
