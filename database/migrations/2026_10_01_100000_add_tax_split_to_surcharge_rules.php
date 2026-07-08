<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_100000_add_tax_split_to_surcharge_rules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Steuerfrei/-pflichtig-Splitting der Zuschläge (Feature 005, Rang 36 —
 * rescoped): je Regel eine konfigurierbare steuerfreie Obergrenze in Prozent
 * (§ 3b EStG als Konfiguration, nicht hart kodiert) plus die Lohnart des
 * steuerpflichtigen Anteils. Der €-Grundlohn-Deckel bleibt bewusst Sache der
 * externen Lohnrechnung (Export ist stundenbasiert ohne Lohnsatz).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('surcharge_rules', function (Blueprint $table): void {
            // Steuerfrei bis … % (null = kein Split, alles über wage_type_code).
            $table->decimal('tax_free_limit_pct', 5, 2)->nullable()->after('wage_type_code');
            // Lohnart des steuerpflichtigen Anteils (Pflicht, sobald limit < percentage).
            $table->string('taxable_wage_type_code', 20)->nullable()->after('tax_free_limit_pct');
        });
    }

    public function down(): void {
        Schema::table('surcharge_rules', function (Blueprint $table): void {
            $table->dropColumn(['tax_free_limit_pct', 'taxable_wage_type_code']);
        });
    }
};
