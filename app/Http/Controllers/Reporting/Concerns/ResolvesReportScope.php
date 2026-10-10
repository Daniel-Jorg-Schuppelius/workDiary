<?php
/*
 * Created on   : Thu Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesReportScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting\Concerns;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Mine/Team-Sichtweite der Report-Controller: `team` nur für Admins oder
 * Inhaber des Fachrechts, das auch die Fachliste öffnet (MVP-460, MVP-1091),
 * alles andere fällt auf `mine` zurück.
 */
trait ResolvesReportScope {
    protected function resolveScope(Request $request, bool $seesAll): string {
        $scope = $request->string('scope', 'mine')->toString();
        if ($scope !== 'team' || ! $seesAll) {
            $scope = 'mine';
        }

        return $scope;
    }

    /**
     * Scope + Admin-Status in einem Schritt.
     *
     * @return array{string, bool} [$scope, $isAdmin]
     */
    protected function resolveScopeWithAdmin(Request $request): array {
        $isAdmin = $this->viewerIsAdmin();

        return [$this->resolveScope($request, $isAdmin), $isAdmin];
    }

    /**
     * Scope + Org-weite Zeit-Sicht in einem Schritt — für Reports, deren
     * Team-Ansicht auch der Buchhaltung offensteht (Sicht, keine Admin-UI).
     *
     * @return array{string, bool} [$scope, $seesAllTimes]
     */
    protected function resolveScopeWithVisibility(Request $request): array {
        return $this->resolveScopeWithPermission($request, Permission::TimeEntryViewAny);
    }

    /**
     * Scope + Team-Sicht über ein Fachrecht in einem Schritt.
     *
     * @return array{string, bool} [$scope, $seesTeam]
     */
    protected function resolveScopeWithPermission(Request $request, Permission $permission): array {
        $seesTeam = $this->viewerHolds($permission);

        return [$this->resolveScope($request, $seesTeam), $seesTeam];
    }

    /** Admin-Status des angemeldeten Nutzers. */
    protected function viewerIsAdmin(): bool {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    /** Org-weite Zeit-Sicht: Admin oder Permission timeEntry.viewAny (E1, MVP-460). */
    protected function viewerSeesAllTimes(): bool {
        return $this->viewerHolds(Permission::TimeEntryViewAny);
    }

    protected function viewerHolds(Permission $permission): bool {
        $user = Auth::user();

        return $user instanceof User
            && ($user->isAdmin() || $user->hasEffectivePermission($permission->value));
    }
}
