<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_25_140100_add_organization_id_to_tenant_child_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-Trennung für Kind-Tabellen, deren Org-Zugehörigkeit bislang
 * nur transitiv über den Parent ergeben konnte. Ohne eigene Spalte
 * konnte ein direkter Zugriff per ID (z. B. /events/{id}, signierte
 * URL) Datensätze fremder Organisationen liefern.
 *
 * - comments        ← polymorphes commentable (Backfill je Typ)
 * - event_reminders ← events.organization_id
 * - push_subscriptions, flex_balances ← users.organization_id
 *
 * Spalten sind nullable, damit Bestandsdaten ohne Parent-Org (alte
 * Imports, Legacy) nicht blockieren. Der OrganizationScope filtert
 * NULL-Records nicht heraus — die Backfill-Schleifen sollen daher
 * vollständig laufen.
 */
return new class extends Migration {
    /** @var list<string> */
    private array $tables = [
        'comments',
        'event_reminders',
        'push_subscriptions',
        'flex_balances',
    ];

    public function up(): void {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->nullOnDelete();

                $blueprint->index('organization_id', "idx_{$table}_org");
            });
        }

        $this->backfillComments();
        $this->backfillEventReminders();
        $this->backfillFromUser('push_subscriptions');
        $this->backfillFromUser('flex_balances');
    }

    public function down(): void {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropForeign(['organization_id']);
                $blueprint->dropIndex("idx_{$table}_org");
                $blueprint->dropColumn('organization_id');
            });
        }
    }

    private function backfillComments(): void {
        $types = DB::table('comments')
            ->select('commentable_type')
            ->whereNotNull('commentable_type')
            ->distinct()
            ->pluck('commentable_type');

        foreach ($types as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = new $class;
            $parentTable = $model->getTable();

            if (! Schema::hasColumn($parentTable, 'organization_id')) {
                continue;
            }

            $this->copyFromParent('comments', $parentTable, 'commentable_id', ['commentable_type' => $class]);
        }
    }

    private function backfillEventReminders(): void {
        $this->copyFromParent('event_reminders', 'events', 'event_id');
    }

    private function backfillFromUser(string $table): void {
        $this->copyFromParent($table, 'users', 'user_id');
    }

    /**
     * Portabler Org-Backfill: kopiert organization_id vom Parent in
     * die Kind-Tabelle. Funktioniert auf MySQL, SQLite und Postgres,
     * weil keine SQL-dialektspezifischen UPDATE-JOINs verwendet werden.
     *
     * @param  array<string, mixed>  $extraWhere  Optionale Zusatz-Where-Klauseln auf die Kind-Tabelle (z. B. polymorpher Typ-Filter).
     */
    private function copyFromParent(string $childTable, string $parentTable, string $fkColumn, array $extraWhere = []): void {
        DB::table($parentTable)
            ->select(['id', 'organization_id'])
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->chunk(500, function ($parents) use ($childTable, $fkColumn, $extraWhere): void {
                foreach ($parents as $parent) {
                    DB::table($childTable)
                        ->where($fkColumn, $parent->id)
                        ->whereNull('organization_id')
                        ->where($extraWhere)
                        ->update(['organization_id' => $parent->organization_id]);
                }
            });
    }
};
