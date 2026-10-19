<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnforcePortalPreview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\User\Permission;
use App\Models\Platform\{Organization, User};
use App\Services\CustomerPortal\PortalPreview;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};
use Symfony\Component\HttpFoundation\Response;

/**
 * Vorschau als Kunde, Teil 2 (Phase 153, MVP-1230): läuft nach dem
 * Organisationskontext und erzwingt je Request
 *
 *  - das Recht `customerPortal.preview` der Mitarbeiterin und die Organisation
 *    des Zustands (Org-Wechsel, Rechteverlust beenden die Vorschau),
 *  - nur lesend: jede Methode außer GET/HEAD/OPTIONS antwortet 403,
 *  - Sperrliste für Anmeldemittel nach Routenname UND Pfad (Lehre aus dem
 *    Sicherheitsaudit 2026-09-13: Namensbausteine allein sind zerbrechlich),
 *  - „Abmelden“ im Portal beendet die Vorschau, nicht die Mitarbeiter-Sitzung.
 */
class EnforcePortalPreview {
    /** Auch lesend gesperrt: Seiten mit Anmeldemitteln des Kunden. */
    private const BLOCKED_ROUTE_PREFIXES = [
        'customer.2fa.',
        'customer.two-factor.',
        'customer.password.',
        'customer.profile.email.',
    ];

    private const BLOCKED_PATH_PARTS = [
        'two-factor',
        'zwei-faktor',
        'passwort',
        'password',
        'webauthn',
    ];

    public function __construct(private readonly PortalPreview $preview) {}

    public function handle(Request $request, Closure $next): Response {
        $state = $this->preview->activeState($request);
        if ($state === null) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($routeName === 'customer.preview.stop') {
            return $next($request);
        }

        $actor = Auth::guard('web')->user();
        if (! $actor instanceof User || ! $this->actorMayPreview($actor)) {
            return $this->preview->endAndRedirect($request, PortalPreview::REASON_PERMISSION_LOST);
        }

        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $organization instanceof Organization || (int) $organization->getKey() !== (int) ($state['organization_id'] ?? 0)) {
            return $this->preview->endAndRedirect($request, PortalPreview::REASON_ORGANIZATION_CHANGED);
        }

        if ($routeName === 'customer.logout') {
            return $this->preview->endAndRedirect($request, PortalPreview::REASON_MANUAL);
        }

        foreach (self::BLOCKED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                abort(Response::HTTP_FORBIDDEN, __('customer_portal.preview.blocked_page'));
            }
        }
        $path = mb_strtolower($request->path());
        foreach (self::BLOCKED_PATH_PARTS as $part) {
            if (str_contains($path, $part)) {
                abort(Response::HTTP_FORBIDDEN, __('customer_portal.preview.blocked_page'));
            }
        }

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            abort(Response::HTTP_FORBIDDEN, __('customer_portal.preview.blocked'));
        }

        return $next($request);
    }

    /**
     * Das Recht der Mitarbeiterin hängt am Guard `web`; nach `auth:customer`
     * steht der Standard-Guard (und damit Spaties Guard-Auflösung) auf
     * `customer`, und `customerPortal.preview` existiert dort nicht. Für die
     * Prüfung kurz auf `web` schalten, danach zurück.
     */
    private function actorMayPreview(User $actor): bool {
        $previous = Auth::getDefaultDriver();
        Auth::shouldUse('web');
        try {
            return $actor->isAdmin() || Gate::forUser($actor)->allows(Permission::CustomerPortalPreview->value);
        } finally {
            Auth::shouldUse($previous);
        }
    }
}
