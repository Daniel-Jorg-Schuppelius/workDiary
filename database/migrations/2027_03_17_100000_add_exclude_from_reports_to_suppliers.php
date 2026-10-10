<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_17_100000_add_exclude_from_reports_to_suppliers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lieferanten aus den Lieferantenauswertungen ausblenden (MVP-1121), etwa
 * Finanzamt oder Krankenkasse: Steuern und Abgaben sind keine Einkäufe.
 * Gleicher Name wie customers.exclude_from_reports. Lief zuerst als
 * 2027_03_15_100000, daher die Spaltenprüfung.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('suppliers', 'exclude_from_reports')) {
            return;
        }
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->boolean('exclude_from_reports')->default(false)->after('active');
        });
    }

    public function down(): void {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn('exclude_from_reports');
        });
    }
};
