<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequiresTeamReportAccess.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting\Concerns;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use Illuminate\Support\Facades\Auth;

/**
 * Team-Auswertungen über die ganze Organisation (Kunden-, Auftragsart- und
 * Produktanalyse samt Drilldowns): nur Admin oder `report.view` — wie
 * Kundenwert und Kundenbindung. Vorher sah jeder Mitarbeiter Stunden und
 * Eskalationen beliebiger Kollegen sowie offene Punkte und Defektprotokolle
 * beliebiger Kunden (Sicherheitsaudit 2026-09-17, authz-report-1).
 */
trait RequiresTeamReportAccess {
    public static function mayViewTeamReports(?User $user): bool {
        return $user instanceof User && ($user->isAdmin() || $user->can(Permission::ReportView->value));
    }

    protected function authorizeTeamReport(): void {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless(self::mayViewTeamReports($user), 403);
    }
}
