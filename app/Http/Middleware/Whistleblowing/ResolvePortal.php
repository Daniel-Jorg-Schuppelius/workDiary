<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvePortal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Whistleblowing;

use App\Models\Whistleblowing\Portal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Loest die Organisation ausschliesslich ueber den oeffentlichen Portal-Slug auf
 * (Abschnitt 6.2). Ein unbekanntes oder deaktiviertes Portal liefert 404 (keine
 * Information ueber Existenz). Bindet die Organisation an den Container, damit
 * tenant-scoped Modelle korrekt arbeiten – ganz ohne Auth-Kontext.
 */
class ResolvePortal {
    public function handle(Request $request, Closure $next): Response {
        $slug = (string) $request->route('portal');

        $portal = Portal::query()
            ->withoutGlobalScopes()
            ->where('public_slug', $slug)
            ->where('is_enabled', true)
            ->first();

        if (! $portal instanceof Portal) {
            abort(404);
        }

        // Organisation an den Container binden (kein Auth-/Org-Context-Middleware).
        $org = $portal->organization;
        if ($org !== null) {
            app()->instance('currentOrganization', $org);
        }

        $request->attributes->set('wb_portal', $portal);

        return $next($request);
    }
}
