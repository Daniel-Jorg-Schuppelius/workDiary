<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_11_100200_retire_draft_status_of_postings_and_budget_requests.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MVP-1517: „Entwurf“ war bei Stellenveröffentlichung und Budgetantrag nur
 * Spaltenvorgabe, der Code legt beide gleich freigegeben bzw. in Freigabe an.
 * Der Enum-Fall entfällt; Zeilen mit dem Vorgabewert erhalten den Stand, den
 * der Code beim Anlegen setzt — die Veröffentlichung bleibt dabei unsichtbar.
 * Die neue Vorgabe setzt nur MySQL/MariaDB: SQLite baute die Tabelle sonst neu.
 */
return new class extends Migration {
    /** @var array<string, string> Tabelle → Stand statt „draft“ */
    private const array TARGETS = [
        'job_postings' => 'closed',
        'investment_budget_requests' => 'in_approval',
    ];

    public function up(): void {
        foreach (self::TARGETS as $table => $status) {
            DB::table($table)->where('status', 'draft')->update(['status' => $status]);
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE `{$table}` ALTER COLUMN `status` SET DEFAULT '{$status}'");
            }
        }
    }

    public function down(): void {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        foreach (array_keys(self::TARGETS) as $table) {
            DB::statement("ALTER TABLE `{$table}` ALTER COLUMN `status` SET DEFAULT 'draft'");
        }
    }
};
