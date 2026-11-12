<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_280200_drop_procedure_template_code_from_maintenance_plans.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1619 (G-08): Welche Prozedur zu einem Asset gehört, legt die
 * Prozedurvorlage über Kategorie, Produkt, SLA-Vertrag oder Kunde fest
 * (MVP-1571). Der Code am Wartungsplan und an seiner Vorlage wurde nie
 * gelesen und von keinem Branchenprofil gesetzt.
 */
return new class extends Migration {
    public function up(): void {
        foreach (['maintenance_plans', 'maintenance_plan_templates'] as $table) {
            if (Schema::hasColumn($table, 'procedure_template_code')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('procedure_template_code');
                });
            }
        }
    }

    public function down(): void {
        foreach (['maintenance_plans', 'maintenance_plan_templates'] as $table) {
            if (! Schema::hasColumn($table, 'procedure_template_code')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->string('procedure_template_code', 60)->nullable();
                });
            }
        }
    }
};
