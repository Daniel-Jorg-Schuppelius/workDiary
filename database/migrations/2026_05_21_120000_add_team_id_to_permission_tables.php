<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_21_120000_add_team_id_to_permission_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'team_id';

        // roles: team_id nullable + Index. Eindeutigkeit (team_id, name, guard_name)
        // damit dieselbe Rollen-Bezeichnung in mehreren Organisationen existieren darf,
        // ohne mit globalen Rollen (team_id = null) zu kollidieren.
        if (! Schema::hasColumn($tableNames['roles'], $teamForeignKey)) {
            Schema::table($tableNames['roles'], static function (Blueprint $table) use ($teamForeignKey): void {
                $table->unsignedBigInteger($teamForeignKey)->nullable()->after('id');
                $table->index($teamForeignKey, 'roles_team_foreign_key_index');
            });

            // Bestehende Unique(name, guard_name) entfernen und durch (team_id, name, guard_name) ersetzen.
            Schema::table($tableNames['roles'], static function (Blueprint $table) use ($teamForeignKey): void {
                try {
                    $table->dropUnique(['name', 'guard_name']);
                } catch (\Throwable) {
                    // Index existiert evtl. unter anderem Namen — ignorieren.
                }
                $table->unique([$teamForeignKey, 'name', 'guard_name'], 'roles_team_name_guard_unique');
            });
        }

        if (! Schema::hasColumn($tableNames['model_has_roles'], $teamForeignKey)) {
            Schema::table($tableNames['model_has_roles'], static function (Blueprint $table) use ($teamForeignKey): void {
                $table->unsignedBigInteger($teamForeignKey)->nullable()->after('model_id');
                $table->index($teamForeignKey, 'model_has_roles_team_foreign_key_index');
            });
        }

        if (! Schema::hasColumn($tableNames['model_has_permissions'], $teamForeignKey)) {
            Schema::table($tableNames['model_has_permissions'], static function (Blueprint $table) use ($teamForeignKey): void {
                $table->unsignedBigInteger($teamForeignKey)->nullable()->after('model_id');
                $table->index($teamForeignKey, 'model_has_permissions_team_foreign_key_index');
            });
        }
    }

    public function down(): void {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'team_id';

        if (Schema::hasColumn($tableNames['roles'], $teamForeignKey)) {
            Schema::table($tableNames['roles'], static function (Blueprint $table) use ($teamForeignKey): void {
                try {
                    $table->dropUnique('roles_team_name_guard_unique');
                } catch (\Throwable) {
                }
                try {
                    $table->dropIndex('roles_team_foreign_key_index');
                } catch (\Throwable) {
                }
                $table->dropColumn($teamForeignKey);
                $table->unique(['name', 'guard_name']);
            });
        }

        if (Schema::hasColumn($tableNames['model_has_roles'], $teamForeignKey)) {
            Schema::table($tableNames['model_has_roles'], static function (Blueprint $table) use ($teamForeignKey): void {
                try {
                    $table->dropIndex('model_has_roles_team_foreign_key_index');
                } catch (\Throwable) {
                }
                $table->dropColumn($teamForeignKey);
            });
        }

        if (Schema::hasColumn($tableNames['model_has_permissions'], $teamForeignKey)) {
            Schema::table($tableNames['model_has_permissions'], static function (Blueprint $table) use ($teamForeignKey): void {
                try {
                    $table->dropIndex('model_has_permissions_team_foreign_key_index');
                } catch (\Throwable) {
                }
                $table->dropColumn($teamForeignKey);
            });
        }
    }
};
