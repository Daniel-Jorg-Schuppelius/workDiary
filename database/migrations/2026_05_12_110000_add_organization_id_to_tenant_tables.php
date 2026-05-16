<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_12_110000_add_organization_id_to_tenant_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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

    public function up(): void {
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

    public function down(): void {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign(['organization_id']);
                $blueprint->dropIndex("idx_{$table}_org");
                $blueprint->dropColumn('organization_id');
            });
        }
    }
};
