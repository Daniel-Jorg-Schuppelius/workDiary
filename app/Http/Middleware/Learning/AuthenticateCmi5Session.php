<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuthenticateCmi5Session.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Learning;

use App\Enums\Security\SecurityEventType;
use App\Models\Learning\LearningCmi5Session;
use App\Models\Platform\Organization;
use App\Services\Security\SecurityEventLogger;
use Closure;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\{JsonResponse, Request};
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Meldet eine AU über den Token aus der Fetch-URL an (cmi5 8.2.3). Der Header
 * wird gehasht und gegen die Sitzungen aufgelöst; danach gilt der Kontext der
 * Organisation, zu der die Sitzung gehört.
 */
final class AuthenticateCmi5Session {
    public const ATTRIBUTE = 'cmi5_session';

    public function handle(Request $request, Closure $next): Response {
        if (preg_match('/^Basic\s+(\S+)$/D', (string) $request->headers->get('Authorization', ''), $match) !== 1) {
            return $this->unauthorized();
        }

        $session = LearningCmi5Session::query()->withoutGlobalScopes()
            ->where('auth_token_hash', CryptoHelper::hash($match[1]))
            ->first();

        if ($session === null) {
            // fail2ban-Signal wie bei den übrigen Token-Endpunkten.
            app(SecurityEventLogger::class)->log(SecurityEventType::ApiTokenInvalid, ['surface' => 'cmi5-lrs']);

            return $this->unauthorized();
        }

        if ($session->abandoned_at !== null || $session->expires_at->isPast()) {
            return $this->unauthorized();
        }

        $organization = Organization::query()->whereKey($session->organization_id)->first();

        if (! $organization instanceof Organization) {
            return $this->unauthorized();
        }

        app()->instance('currentOrganization', $organization);
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        $request->attributes->set(self::ATTRIBUTE, $session);

        return $next($request);
    }

    private function unauthorized(): JsonResponse {
        return response()->json(['error' => 'unauthorized'], 401);
    }
}
