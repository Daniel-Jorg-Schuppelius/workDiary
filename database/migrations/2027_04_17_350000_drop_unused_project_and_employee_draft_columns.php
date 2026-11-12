<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_350000_drop_unused_project_and_employee_draft_columns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1620: `projects.is_maintenance` und `employee_drafts.planned_start_on`
 * hatten weder Schreiber noch Leser (Gate „Spalte ohne Verwendung“); im Bestand
 * stehen nur die Vorgaben (false bzw. leer).
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('projects', 'is_maintenance')) {
            if (Schema::hasIndex('projects', 'projects_is_maintenance_idx')) {
                Schema::table('projects', function (Blueprint $table): void {
                    $table->dropIndex('projects_is_maintenance_idx');
                });
            }
            Schema::table('projects', function (Blueprint $table): void {
                $table->dropColumn('is_maintenance');
            });
        }

        if (Schema::hasColumn('employee_drafts', 'planned_start_on')) {
            Schema::table('employee_drafts', function (Blueprint $table): void {
                $table->dropColumn('planned_start_on');
            });
        }
    }

    public function down(): void {
        if (! Schema::hasColumn('projects', 'is_maintenance')) {
            Schema::table('projects', function (Blueprint $table): void {
                $table->boolean('is_maintenance')->default(false);
                $table->index('is_maintenance', 'projects_is_maintenance_idx');
            });
        }

        if (! Schema::hasColumn('employee_drafts', 'planned_start_on')) {
            Schema::table('employee_drafts', function (Blueprint $table): void {
                $table->date('planned_start_on')->nullable();
            });
        }
    }
};
