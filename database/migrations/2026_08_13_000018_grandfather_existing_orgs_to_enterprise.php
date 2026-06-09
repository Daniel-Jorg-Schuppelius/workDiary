<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000018_grandfather_existing_orgs_to_enterprise.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bestandsschutz: Vor Einfuehrung des harten Modul-Gatings existierende
 * Organisationen hatten Zugriff auf ALLE Module. Mit dem Gating wuerde der
 * Default-Plan `free` sie aussperren. Daher werden alle bestehenden Orgs auf
 * `enterprise` gesetzt (Grandfathering). Neue Orgs starten weiter auf `free`.
 *
 * Nicht reversibel auf Daten-Ebene (down() ist bewusst ein No-op) – ein
 * pauschales Zuruecksetzen auf `free` wuerde funktionierende Kunden sperren.
 */
return new class extends Migration {
    public function up(): void {
        DB::table('organizations')
            ->whereIn('plan', ['free', ''])
            ->orWhereNull('plan')
            ->update(['plan' => 'enterprise']);
    }

    public function down(): void {
        // Bewusst kein Rollback: Grandfathering nicht automatisch entziehen.
    }
};
