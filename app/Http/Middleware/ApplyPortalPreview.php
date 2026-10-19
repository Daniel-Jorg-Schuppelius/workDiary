<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplyPortalPreview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\CustomerPortal\PortalPreview;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vorschau als Kunde, Teil 1 (Phase 153, MVP-1230): läuft per Prioritätsliste
 * VOR `auth:customer` und setzt das Portalkonto aus dem Vorschau-Zustand der
 * Mitarbeiter-Sitzung auf den customer-Guard — per setUser(), ohne Sitzungs-
 * Login, ohne Login-Ereignis. Hier gibt es noch keinen Organisationskontext
 * und keine Spatie-Team-ID; Recht und Organisation prüft
 * {@see EnforcePortalPreview} danach.
 *
 * Nach dem Request stellt die Middleware den Standard-Guard auf `web` zurück:
 * `auth:customer` schaltet ihn auf `customer`, und der DatabaseSessionHandler
 * schreibt beim Speichern die ID des Standard-Guards in `sessions.user_id` —
 * die Sitzungszeile gehört der Mitarbeiterin, nicht dem Kunden („Letzte
 * Anmeldung“ im Panel, Fernabmeldung des Kontos).
 */
class ApplyPortalPreview {
    public function __construct(private readonly PortalPreview $preview) {}

    public function handle(Request $request, Closure $next): Response {
        $state = $this->preview->state($request);
        if ($state === null || ! $request->routeIs('customer.*') || $request->routeIs('customer.preview.stop')) {
            return $next($request);
        }

        if (! $this->preview->actorMatches($state, Auth::guard('web')->user())) {
            return $this->preview->endAndRedirect($request, PortalPreview::REASON_ACTOR_CHANGED);
        }
        if ($this->preview->isExpired($state)) {
            return $this->preview->endAndRedirect($request, PortalPreview::REASON_EXPIRED);
        }

        $portalUser = $this->preview->resolvePortalUser($state);
        if ($portalUser === null) {
            return $this->preview->endAndRedirect($request, PortalPreview::REASON_ACCOUNT_DEACTIVATED);
        }
        if (! $this->preview->accountUnchanged($state, $portalUser)) {
            return $this->preview->endAndRedirect($request, PortalPreview::REASON_ACCOUNT_CHANGED);
        }

        Auth::guard('customer')->setUser($portalUser);
        $request->attributes->set(PortalPreview::REQUEST_ATTRIBUTE, $state);

        $response = $next($request);

        Auth::shouldUse('web');

        return $response;
    }
}
