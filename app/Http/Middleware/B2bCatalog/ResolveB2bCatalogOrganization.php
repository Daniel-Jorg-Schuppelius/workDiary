<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolveB2bCatalogOrganization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\B2bCatalog;

use App\Models\Organization;
use App\Services\Licensing\ModuleStatusResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Löst die Organisation des öffentlichen Punchout-Katalogs (Feature 099,
 * MVP-457) ausschließlich über den mandantengebundenen `{org}`-Slug auf —
 * Muster {@see \App\Http\Middleware\Careers\ResolveCareerPortal}. Unbekannter
 * Mandant **oder inaktives Modul** liefert 404 statt Upsell: der öffentliche
 * Endpunkt existiert nur für Organisationen, die `module.b2b_katalog` gebucht
 * haben (Entscheidung E1 — kleinere Angriffsfläche).
 */
class ResolveB2bCatalogOrganization {
    public function __construct(private readonly ModuleStatusResolver $modules) {}

    public function handle(Request $request, Closure $next): Response {
        $slug = (string) $request->route('org');

        $organization = Organization::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->first();

        if (! $organization instanceof Organization) {
            abort(404);
        }

        // Org binden, BEVOR gescopte Queries laufen.
        app()->instance('currentOrganization', $organization);

        // `requires => module.lager` (plans.php) hier explizit nachziehen — der
        // ModuleStatusResolver kennt die requires-Kette bewusst nicht.
        if (! $this->modules->isActiveFor($organization, 'module.b2b_katalog')
            || ! $this->modules->isActiveFor($organization, 'module.lager')) {
            abort(404);
        }

        $request->attributes->set('b2b_organization', $organization);

        return $next($request);
    }
}
