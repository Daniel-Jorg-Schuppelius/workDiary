<?php

namespace App\Http\Middleware;

use App\Models\Organization;
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
class SetOrganizationContext {
    public function handle(Request $request, Closure $next): Response {
        if (Auth::check()) {
            /** @var \App\Models\User $user */
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
