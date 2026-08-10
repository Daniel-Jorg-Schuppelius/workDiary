<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnsurePortalCapability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\CustomerPortal\PortalCapability;
use App\Models\{Customer, User};
use App\Services\CustomerPortal\PortalVisibility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routen-Gate der Portal-Bereiche (MVP-511): `portal.capability:diary`.
 * Nicht freigegebene Bereiche antworten kundensicher mit 404 — auch bei
 * direktem Aufruf, geratenen IDs oder alten Links. Wirkt unmittelbar auf
 * bestehende Sessions, da je Request entschieden wird.
 */
class EnsurePortalCapability {
    public function __construct(private readonly PortalVisibility $visibility) {}

    public function handle(Request $request, Closure $next, string $capability): Response {
        $portalUser = $request->user('customer');
        $customer = $portalUser instanceof User ? $portalUser->customer : null;
        $cap = PortalCapability::tryFrom($capability);

        abort_unless(
            $customer instanceof Customer && $cap !== null && $this->visibility->allows($customer, $cap),
            404,
        );

        return $next($request);
    }
}
