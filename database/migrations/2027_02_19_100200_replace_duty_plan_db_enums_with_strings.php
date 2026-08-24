<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_100200_replace_duty_plan_db_enums_with_strings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Vollscan 2026-08-23 (F17): duty_plans.period_type/status waren die einzigen
 * DB-ENUMs im Schema — jeder neue Wert (z. B. „submitted") brauchte ein ALTER
 * TABLE, und SQLite (Tests) behandelte sie ohnehin als varchar. Gültige Werte
 * sichern die PHP-Enums DutyPlanPeriodType/DutyPlanStatus (Model-Casts).
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return; // SQLite kennt kein ENUM; die Spalten sind dort bereits varchar.
        }

        DB::statement("ALTER TABLE duty_plans MODIFY period_type VARCHAR(16) NOT NULL");
        DB::statement("ALTER TABLE duty_plans MODIFY status VARCHAR(16) NOT NULL DEFAULT 'draft'");
    }

    public function down(): void {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE duty_plans MODIFY period_type ENUM('daily', 'weekly', 'monthly') NOT NULL");
        DB::statement("ALTER TABLE duty_plans MODIFY status ENUM('draft', 'submitted', 'published') NOT NULL DEFAULT 'draft'");
    }
};
