<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_15_000000_add_org_scoped_composite_indexes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite-Indizes für die häufigsten org-gescopten Filter. Jede Query läuft
 * über den OrganizationScope (organization_id = ?); Listen filtern/sortieren
 * zusätzlich nach status bzw. Datumsbereich. Die bisherigen Einzelindizes auf
 * status/Datum greifen dabei nicht. Index-Namen explizit und kurz wegen des
 * 64-Zeichen-Limits von MySQL.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->index(['organization_id', 'status'], 'tasks_org_status_idx');
        });

        Schema::table('timesheets', function (Blueprint $table): void {
            $table->index(['organization_id', 'status'], 'timesheets_org_status_idx');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['organization_id', 'status'], 'invoices_org_status_idx');
        });

        Schema::table('sick_leaves', function (Blueprint $table): void {
            $table->index(['organization_id', 'start_date', 'end_date'], 'sick_leaves_org_dates_idx');
        });

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->index(['organization_id', 'start_at'], 'diary_org_start_idx');
        });
    }

    public function down(): void {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_org_status_idx');
        });

        Schema::table('timesheets', function (Blueprint $table): void {
            $table->dropIndex('timesheets_org_status_idx');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_org_status_idx');
        });

        Schema::table('sick_leaves', function (Blueprint $table): void {
            $table->dropIndex('sick_leaves_org_dates_idx');
        });

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropIndex('diary_org_start_idx');
        });
    }
};
