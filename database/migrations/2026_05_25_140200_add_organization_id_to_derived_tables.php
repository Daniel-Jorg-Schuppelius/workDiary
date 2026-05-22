<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_25_140200_add_organization_id_to_derived_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-Trennung für abgeleitete Kind-Tabellen. Diese Datensätze
 * sind in jeder Org einer fachlichen Mutter-Entität zugeordnet und
 * bekommen organization_id als denormalisierte Kopie, damit der
 * OrganizationScope direkt greifen kann – ohne Join, ohne
 * Defense-in-Depth-Lücken bei ID-basierten Zugriffen.
 *
 *  - invoice_items       ← invoices
 *  - material_usages     ← timesheets
 *  - per_diem_days       ← per_diem_trips
 *  - event_user (Pivot)  ← events
 *  - automation_rule_runs ← automation_rules
 */
return new class extends Migration {
    /** @var array<string, array{parent: string, parent_fk: string}> */
    private array $tables = [
        'invoice_items' => ['parent' => 'invoices', 'parent_fk' => 'invoice_id'],
        'material_usages' => ['parent' => 'timesheets', 'parent_fk' => 'timesheet_id'],
        'per_diem_days' => ['parent' => 'per_diem_trips', 'parent_fk' => 'per_diem_trip_id'],
        'event_user' => ['parent' => 'events', 'parent_fk' => 'event_id'],
        'automation_rule_runs' => ['parent' => 'automation_rules', 'parent_fk' => 'rule_id'],
    ];

    public function up(): void {
        foreach (array_keys($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->nullOnDelete();

                $blueprint->index('organization_id', "idx_{$table}_org");
            });
        }

        foreach ($this->tables as $table => $config) {
            $this->copyFromParent($table, $config['parent'], $config['parent_fk']);
        }
    }

    /**
     * Portabler Org-Backfill: kopiert organization_id vom Parent in
     * die Kind-Tabelle. Funktioniert auf MySQL, SQLite und Postgres,
     * weil keine SQL-dialektspezifischen UPDATE-JOINs verwendet werden.
     */
    private function copyFromParent(string $childTable, string $parentTable, string $fkColumn): void {
        DB::table($parentTable)
            ->select(['id', 'organization_id'])
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->chunk(500, function ($parents) use ($childTable, $fkColumn): void {
                foreach ($parents as $parent) {
                    DB::table($childTable)
                        ->where($fkColumn, $parent->id)
                        ->whereNull('organization_id')
                        ->update(['organization_id' => $parent->organization_id]);
                }
            });
    }

    public function down(): void {
        foreach (array_reverse(array_keys($this->tables)) as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropForeign(['organization_id']);
                $blueprint->dropIndex("idx_{$table}_org");
                $blueprint->dropColumn('organization_id');
            });
        }
    }
};
