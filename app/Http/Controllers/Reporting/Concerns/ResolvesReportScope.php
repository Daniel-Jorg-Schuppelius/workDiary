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

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Mine/Team-Sichtweite der Report-Controller: `team` nur für Admins,
 * alles andere fällt auf `mine` zurück.
 */
trait ResolvesReportScope {
    protected function resolveScope(Request $request, bool $isAdmin): string {
        $scope = $request->string('scope', 'mine')->toString();
        if ($scope !== 'team' || ! $isAdmin) {
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

    /** Admin-Status des angemeldeten Nutzers. */
    protected function viewerIsAdmin(): bool {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }
}
