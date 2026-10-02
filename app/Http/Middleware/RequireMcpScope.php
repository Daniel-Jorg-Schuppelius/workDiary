<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequireMcpScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Mcp\OAuth\McpAuthorizationServer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP nur mit ausdrücklich vergebenem Scope (MVP-1065): Vollzugriffs-Token
 * (`*`) stammen aus der Zeit vor MCP und öffnen den Endpunkt nicht. Sanctums
 * `ability:`-Middleware ließe sie durch, weil `*` jede Fähigkeit umfasst.
 */
class RequireMcpScope {
    public function handle(Request $request, Closure $next): Response {
        $abilities = data_get($request->user()?->currentAccessToken(), 'abilities');
        if (! is_array($abilities) || array_intersect($abilities, McpAuthorizationServer::SCOPES) === []) {
            return response()->json(['error' => 'insufficient_scope', 'error_description' => (string) __('mcp.error.scope')], 403);
        }

        return $next($request);
    }
}
