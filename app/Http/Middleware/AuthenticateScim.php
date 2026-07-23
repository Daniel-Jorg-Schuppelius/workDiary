<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuthenticateScim.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\{Organization, ScimToken};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Scim\ScimResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifiziert SCIM-Requests (Feature 057, MVP-121) server-zu-server über
 * einen Bearer-Token: der Header wird SHA-256-gehasht und gegen die aktiven
 * {@see ScimToken} der Organisation aufgelöst. Danach werden Org-Kontext und
 * Spatie-Team-Kontext gebunden und das Enterprise-Modul geprüft — SCIM ist ein
 * Enterprise-Feature (`module.sso`). Fehler kommen SCIM-konform zurück.
 */
class AuthenticateScim {
    public function handle(Request $request, Closure $next): Response {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return ScimResponse::error(401, 'Missing bearer token.');
        }
        $plain = trim(substr($header, 7));
        if ($plain === '') {
            return ScimResponse::error(401, 'Missing bearer token.');
        }

        $token = ScimToken::query()->withoutGlobalScopes()
            ->where('token_hash', ScimToken::hashToken($plain))
            ->whereNull('revoked_at')
            ->first();
        if (! $token instanceof ScimToken) {
            // fail2ban-Signal (Feature 096, MVP-443): Brute-Force auf SCIM-Tokens.
            app(\App\Services\Security\SecurityEventLogger::class)->log(
                \App\Enums\Security\SecurityEventType::ApiTokenInvalid,
                ['surface' => 'scim'],
            );

            return ScimResponse::error(401, 'Invalid or revoked token.');
        }

        $organization = Organization::query()->whereKey($token->organization_id)->first();
        if (! $organization instanceof Organization) {
            return ScimResponse::error(401, 'Invalid token context.');
        }

        // Org- und Spatie-Team-Kontext binden (nötig für Lizenz + Rollen-Team).
        app()->instance('currentOrganization', $organization);
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        // Enterprise-Gating (SCIM = module.sso), nach dem Binden der Organisation.
        if (! app(FeatureFlagResolver::class)->isEnabled('module.sso')) {
            return ScimResponse::error(403, 'SCIM provisioning requires the Enterprise plan.');
        }

        // Aufholpunkt der Nutzung; ohne Events/Audit-Rauschen.
        DB::table('scim_tokens')->where('id', $token->id)->update(['last_used_at' => now()]);

        $request->attributes->set('scim_organization', $organization);

        return $next($request);
    }
}
