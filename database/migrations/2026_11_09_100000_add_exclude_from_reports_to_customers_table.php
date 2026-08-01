<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_09_100000_add_exclude_from_reports_to_customers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 002: Kunden org-weit aus den Auswertungen ausblenden können
 * (z. B. Arbeitgeber-Kunde, der separat abgerechnet wird). Zeiterfassung
 * und Stammdaten bleiben unberührt — das Flag wirkt nur in den
 * kundenbezogenen Reports (Toggle „Ausgeblendete einbeziehen").
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('exclude_from_reports')->default(false)->after('billable');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('exclude_from_reports');
        });
    }
};
