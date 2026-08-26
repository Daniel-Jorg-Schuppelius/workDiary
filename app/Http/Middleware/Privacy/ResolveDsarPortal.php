<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolveDsarPortal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Privacy;

use App\Models\Privacy\DsarPortal;
use App\Services\Licensing\FeatureFlagResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Loest die Organisation des Betroffenenportals ausschliesslich ueber den
 * oeffentlichen Slug auf (G11, MVP-728). Unbekannt oder deaktiviert ⇒ 404 —
 * beides ist ununterscheidbar, damit der Slug-Raum nicht durchprobiert werden
 * kann. Bindet die Organisation an den Container, damit tenant-gescopte
 * Modelle ohne Auth-Kontext korrekt arbeiten.
 */
class ResolveDsarPortal {
    public function handle(Request $request, Closure $next): Response {
        $slug = (string) $request->route('portal');

        $portal = DsarPortal::query()
            ->withoutGlobalScopes()
            ->where('public_slug', $slug)
            ->where('is_enabled', true)
            ->first();

        if (! $portal instanceof DsarPortal) {
            abort(404);
        }

        $org = $portal->organization;
        if ($org !== null) {
            app()->instance('currentOrganization', $org);
        }

        // Modul-Gate bewusst als 404 statt 423 (EnforcePlanModules): eine
        // Upsell-Seite auf einer oeffentlichen URL wuerde verraten, dass es die
        // Organisation gibt und welches Modul ihr fehlt. flush(), weil der
        // Resolver scoped ist und ohne currentOrganization aufgeloest sein kann.
        $features = app(FeatureFlagResolver::class);
        $features->flush();
        if (! $features->isEnabled('module.datenschutz')) {
            abort(404);
        }

        $request->attributes->set('dsar_portal', $portal);

        return $next($request);
    }
}
