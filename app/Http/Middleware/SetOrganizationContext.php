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
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Bindet die aktuelle Organisation des Users als 'currentOrganization' in den Container,
 * worüber OrganizationScope alle tenant-scoped Queries filtert.
 *
 * Einzige Stelle für einen Laufzeit-Wechsel des Org-Kontexts: globale Admins via
 * Session-Override ({@see OrganizationSwitchController}).
 */
class SetOrganizationContext {
    public function handle(Request $request, Closure $next): Response {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            $org = $this->resolveOrganization($request, $user);

            if (! $org instanceof Organization && $this->belongsToBlockedOrganization($user)) {
                $denied = $this->denyBlockedTenant($request);

                if ($denied instanceof Response) {
                    return $denied;
                }
            }

            if ($org instanceof Organization) {
                app()->instance('currentOrganization', $org);

                // Aktive Org als Spatie-Team-Kontext; globale Rollen (team_id = NULL) bleiben überall gültig.
                $registrar = app(PermissionRegistrar::class);
                $registrar->setPermissionsTeamId($org->id);

                // Nach Team-Wechsel beide Spatie-Caches (Registrar + geladene User-Relationen) leeren,
                // sonst werten Policies/isAdmin() gegen den alten Team-Cache aus.
                $registrar->forgetCachedPermissions();
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');
            }
        }

        return $next($request);
    }

    /**
     * Gehört der Nutzer zu einer Organisation, die nicht (mehr) aktiv ist?
     *
     * **Der gefährliche Fall ist nicht das Sperren, sondern das Weiterlaufen**
     * (Sicherheitsscan 2026-08-23, S-04): Ohne gebundene Organisation wird
     * `OrganizationScope` zum No-Op — jede Liste, deren Policy `viewAny` mit
     * `true` beantwortet, lief dann über **alle** Mandanten. Ein Mitarbeiter
     * einer wegen Zahlungsverzug abgeschalteten Organisation konnte sich
     * weiterhin anmelden und Kundenstamm, Projekte und Chatverläufe aller
     * anderen Mandanten lesen.
     *
     * Geprüft wird nur der belegbare Fall: der Nutzer **hat** eine
     * Organisation, sie ist aber inaktiv oder verschwunden. Wer gar keine
     * Organisation trägt (frisch angelegte Betreiberkonten, Installationslauf),
     * bleibt wie bisher ungebunden — dort gibt es keinen Mandanten, dessen
     * Grenze verletzt werden könnte.
     */
    private function belongsToBlockedOrganization(User $user): bool {
        if ($user->isGlobalAdmin() || ! $user->organization_id) {
            return false;
        }

        $own = $user->relationLoaded('organization')
            ? $user->organization
            : $user->load('organization')->organization;

        return ! ($own instanceof Organization && $own->is_active);
    }

    /**
     * Abweisen statt ungescopt weiterlaufen.
     *
     * Rückgabe `null` heißt „durchlassen": der Logout muss erreichbar bleiben,
     * sonst säße der Nutzer in einer Sitzung fest, die er nicht beenden kann.
     */
    private function denyBlockedTenant(Request $request): ?Response {
        $name = (string) ($request->route()?->getName() ?? '');

        if ($name === 'logout' || str_starts_with($name, 'logout.')) {
            return null;
        }

        $message = (string) __('Diese Organisation ist deaktiviert. Bitte wenden Sie sich an den Betreiber.');

        if ($request->expectsJson()) {
            return new JsonResponse(['error' => 'organization_inactive', 'message' => $message], Response::HTTP_LOCKED);
        }

        throw new HttpException(Response::HTTP_LOCKED, $message);
    }

    private function resolveOrganization(Request $request, User $user): ?Organization {
        // 1) Session-Override (nur globale Plattform-Betreiber) — nur AKTIVE
        //    Org akzeptieren. isAdmin() reichte nicht: ein org-lokaler Admin
        //    könnte sonst in jede fremde Org springen (Cross-Tenant).
        if ($user->isGlobalAdmin() && $request->hasSession()) {
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
        //    Betreiber nicht ausgesperrt wird und die Verwaltung weiterhin
        //    nutzbar bleibt. Diese Bindung ist bewusst nur in-memory.
        //
        //    isGlobalAdmin(), nicht isAdmin(): ein Org-Admin einer
        //    deaktivierten Organisation landete sonst in der ERSTEN aktiven
        //    Fremd-Organisation — mit seinem Policy-Bypass im Gepäck
        //    (Sicherheitsscan 2026-08-23, S-01/S-04).
        if ($user->isGlobalAdmin()) {
            $first = Organization::query()->where('is_active', true)->orderBy('id')->first();
            if ($first instanceof Organization) {
                return $first;
            }
        }

        return null;
    }
}
