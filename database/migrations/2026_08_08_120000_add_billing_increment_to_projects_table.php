<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_08_120000_add_billing_increment_to_projects_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abrechnungs-Taktung pro Projekt.
 *
 * - billing_increment_minutes:    Taktung in Minuten (1 = minutengenau).
 *                                 NULL = vom Parent-Projekt / Kunden erben.
 * - billing_grouping_gap_minutes: max. Lücke zwischen Einträgen, die noch
 *                                 zusammengefasst werden (0 = aus).
 *                                 NULL = erben.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedSmallInteger('billing_increment_minutes')->nullable()->after('budget_type');
            $table->unsignedSmallInteger('billing_grouping_gap_minutes')->nullable()->after('billing_increment_minutes');
        });
    }

    public function down(): void {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['billing_increment_minutes', 'billing_grouping_gap_minutes']);
        });
    }
};
