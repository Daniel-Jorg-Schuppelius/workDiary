<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SetOrganizationContext.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Middleware;

use App\Http\Controllers\OrganizationSwitchController;
use App\Models\{Organization, User};
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current organization from the authenticated user and binds it
 * into the service container as 'currentOrganization'.
 *
 * This enables OrganizationScope to automatically filter all tenant-scoped
 * Eloquent queries to the correct organization.
 *
 * Globale Admins (Spatie-Rolle "admin") dürfen über einen Session-Override
 * (siehe OrganizationSwitchController) eine andere Organisation als die in
 * users.organization_id eingetragene aktivieren. Dies ist die einzige Stelle,
 * an der ein Wechsel des Org-Kontexts zur Laufzeit zulässig ist.
 */
class SetOrganizationContext {
    public function handle(Request $request, Closure $next): Response {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            $org = $this->resolveOrganization($request, $user);

            if ($org instanceof Organization) {
                app()->instance('currentOrganization', $org);

                // Spatie-Teams: aktive Organisation als Team-Kontext setzen,
                // damit Org-spezifische Rollen-Zuweisungen ausgewertet werden.
                // Globale Rollen (team_id = NULL, z. B. der Plattform-"admin")
                // bleiben in jedem Kontext gültig.
                $registrar = app(PermissionRegistrar::class);
                $registrar->setPermissionsTeamId($org->id);

                // Spatie's HasRoles cached die geladenen Rollen/Permissions
                // sowohl global im Registrar als auch in den geladenen
                // Eloquent-Relationen des Users. Nach einem Wechsel des
                // Team-Kontexts (Admin-Override, frischer Request nach
                // Login, Test-Setup mit mehreren `actingAs`-Calls) muss
                // beides zurückgesetzt werden, sonst werten Policies und
                // `isAdmin()`-Checks gegen den alten Team-Cache aus.
                $registrar->forgetCachedPermissions();
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');
            }
        }

        return $next($request);
    }

    private function resolveOrganization(Request $request, User $user): ?Organization {
        // 1) Session-Override (nur für Admins) — nur AKTIVE Org akzeptieren.
        if ($user->isAdmin() && $request->hasSession()) {
            $overrideId = $request->session()->get(OrganizationSwitchController::SESSION_KEY);
            if (is_int($overrideId) || (is_string($overrideId) && ctype_digit($overrideId))) {
                $override = Organization::query()->find((int) $overrideId);
                if ($override instanceof Organization && $override->is_active) {
                    return $override;
                }
                // ungültige, gelöschte oder deaktivierte Org: Override verwerfen,
                // damit auf den Standard-Kontext zurückgefallen wird.
                $request->session()->forget(OrganizationSwitchController::SESSION_KEY);
            }
        }

        // 2) Standard: Org des Benutzers — nur wenn aktiv.
        if ($user->organization_id) {
            $own = $user->relationLoaded('organization')
                ? $user->organization
                : $user->load('organization')->organization;
            if ($own instanceof Organization && $own->is_active) {
                return $own;
            }
        }

        // 3) Fallback nur für globale Admins: falls weder Override noch
        //    eigene Org auflösbar/aktiv sind (z. B. nach Deaktivieren der eigenen
        //    Org), erste verfügbare AKTIVE Organisation aktivieren, damit der
        //    Admin nicht ausgesperrt wird und die Verwaltung weiterhin nutzbar
        //    bleibt. Diese Bindung ist bewusst nur in-memory.
        if ($user->isAdmin()) {
            $first = Organization::query()->where('is_active', true)->orderBy('id')->first();
            if ($first instanceof Organization) {
                return $first;
            }
        }

        return null;
    }
}
