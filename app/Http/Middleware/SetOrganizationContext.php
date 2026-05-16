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

use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current organization from the authenticated user and binds it
 * into the service container as 'currentOrganization'.
 *
 * This enables OrganizationScope to automatically filter all tenant-scoped
 * Eloquent queries to the correct organization.
 */
class SetOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($user->organization_id) {
                // Eager-load only once; load() is a no-op if already loaded.
                $org = $user->relationLoaded('organization')
                    ? $user->organization
                    : $user->load('organization')->organization;

                if ($org instanceof Organization) {
                    app()->instance('currentOrganization', $org);
                }
            }
        }

        return $next($request);
    }
}
