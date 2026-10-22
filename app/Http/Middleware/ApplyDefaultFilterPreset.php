<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplyDefaultFilterPreset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Platform\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Öffnet eine Liste ohne Filter, lädt sie mit dem Standardfilter der Person
 * für diesen Bereich (Routenname). „Zurücksetzen“ in der Filterleiste hängt
 * `preset=none` an und bleibt damit beim Grundzustand.
 */
class ApplyDefaultFilterPreset {
    public function handle(Request $request, Closure $next): Response {
        $name = $request->route()?->getName();
        $user = $request->user();
        if ($name === null || ! str_ends_with($name, '.index') || ! $request->isMethod('GET')
            || $request->getQueryString() !== null || $request->ajax() || $request->expectsJson()
            || ! $user instanceof User) {
            return $next($request);
        }

        $query = $user->filterPresets()->getQuery()->reorder()
            ->where('scope', $name)
            ->where('is_default', true)
            ->first(['id', 'query'])?->query;
        if (! is_array($query) || $query === []) {
            return $next($request);
        }

        return redirect()->to($request->url() . '?' . http_build_query($query));
    }
}
