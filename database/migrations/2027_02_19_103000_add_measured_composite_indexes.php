<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_103000_add_measured_composite_indexes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kombi-Indizes für die gemessenen Listen-Brennpunkte (Vollscan 2026-08-23,
 * A14/F16, MVP-722). Angelegt wird nur, was die Messung mit
 * `perf:seed-load --explain` (3 Organisationen, 50.000 `time_entries`,
 * 100.000 `audit_logs`, 5.000 `quotes`) belegt hat:
 *
 *   audit_logs (organization_id, created_at) 25,7 ms → 0,5 ms
 *       (`Using filesort` entfällt, die Liste liest die Indexordnung)
 *   time_entries (organization_id, date)      3,3 ms → 1,6 ms  (rows 4186 → 1426)
 *   time_entries (user_id, date)              3,5 ms → 1,4 ms
 *   time_entries (project_id, date)           3,4 ms → 1,4 ms
 *   quotes (organization_id, status)          0,8 ms → 0,5 ms
 *       (type=index über PRIMARY → type=ref; der Gewinn wächst mit der Zahl
 *        der Angebote je Organisation)
 *
 * Bewusst NICHT angelegt: `travel_logs` — dort existieren
 * (organization_id, date) und (user_id, date) bereits; die Messung zeigte den
 * Index nach der A8-Umstellung sauber genutzt (rows 10.095 → 589).
 *
 * Die vorhandenen Einzelspalten-Indizes auf `time_entries` bleiben: sie tragen
 * Fremdschlüssel und ihr Abbau ist eine eigene, eigenständig zu messende
 * Entscheidung.
 */
return new class extends Migration {
    /** Tabelle → [Indexname => Spalten]; Namen < 64 Zeichen, DB-weit eindeutig. */
    private const INDEXES = [
        'audit_logs' => ['audit_logs_org_created_idx' => ['organization_id', 'created_at']],
        'time_entries' => [
            'te_org_date_idx' => ['organization_id', 'date'],
            'te_user_date_idx' => ['user_id', 'date'],
            'te_project_date_idx' => ['project_id', 'date'],
        ],
        'quotes' => ['qte_org_status_idx' => ['organization_id', 'status']],
    ];

    public function up(): void {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $name => $columns) {
                    $blueprint->index($columns, $name);
                }
            });
        }
    }

    public function down(): void {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach (array_keys($indexes) as $name) {
                    $blueprint->dropIndex($name);
                }
            });
        }
    }
};
