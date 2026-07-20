<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolveCareerPortal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Careers;

use App\Models\Organization;
use App\Support\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Löst die Organisation des öffentlichen Karrierebereichs (MVP-437)
 * ausschließlich über den mandantengebundenen `{org}`-Slug auf — kein
 * Auth-/Org-Context-Middleware. Ein unbekannter Mandant **oder ein
 * deaktiviertes Portal** liefert 404 (keine Information über Existenz;
 * spiegelt {@see \App\Http\Middleware\Whistleblowing\ResolvePortal}).
 *
 * Bindet `currentOrganization` an den Container, damit `OrganizationScope`,
 * `Setting::get()` und das nachgelagerte {@see \App\Http\Middleware\EnforcePlanModules}
 * (careers.* → module.applications) tenant-korrekt arbeiten.
 */
class ResolveCareerPortal {
    public function handle(Request $request, Closure $next): Response {
        $slug = (string) $request->route('org');

        $organization = Organization::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->first();

        if (! $organization instanceof Organization) {
            abort(404);
        }

        // Org binden, BEVOR das Opt-in-Setting gelesen wird.
        app()->instance('currentOrganization', $organization);

        if (! (bool) Setting::get('applications.portal.enabled', false)) {
            abort(404);
        }

        $request->attributes->set('career_organization', $organization);

        return $next($request);
    }
}
