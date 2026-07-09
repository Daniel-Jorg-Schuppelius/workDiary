<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnforceMaintenanceMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpFoundation\Response;

/**
 * Wartungsmodus pro Mandant (Restpunkte Rang 65): Ist für die aktuelle
 * Organisation `settings.maintenance.enabled` gesetzt (und `until` nicht
 * überschritten), erhalten Nicht-Admins eine 503-Seite mit Retry-After.
 * Org-Admins arbeiten weiter und sehen einen Banner (Layout), damit sie den
 * Modus wieder abschalten können. Tokenbasierte Ingest-Endpunkte (Terminal/
 * CTI/Standort) laufen außerhalb dieser Middleware und werden — sofern
 * `block_ingest` nicht gesetzt ist — bewusst nicht unterbrochen (Stempeln
 * während der Wartung); die Controller prüfen das Flag selbst nach der
 * Token-Auflösung.
 */
class EnforceMaintenanceMode {
    /** Routen, die trotz Wartung erreichbar bleiben (An-/Abmeldung). */
    private const ALLOWED_ROUTE_PREFIXES = [
        'login',
        'logout',
        'password.',
        'two-factor.',
    ];

    public function handle(Request $request, Closure $next): Response {
        $org = $this->currentOrganization();

        // Geplante Wartungsfenster (MVP-055): system- oder org-weit,
        // zeitbasiert wirksam, optional Nur-Lesen-Betrieb.
        $window = \App\Models\MaintenanceWindow::effectiveFor($org?->id !== null ? (int) $org->id : null);
        if ($window !== null) {
            $user = $request->user();
            $isBypassed = $user !== null && $user->isAdmin();
            if ($user !== null && ! $this->isAllowedRoute($request) && ! $isBypassed) {
                if (! $window->read_only) {
                    return $this->denyWindow($request, $window);
                }
                if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                    return $this->denyWindow($request, $window);
                }
            }
        }

        if (! $org instanceof Organization || ! $org->inMaintenance()) {
            return $next($request);
        }

        // Ohne Login (Login-/Passwort-Routen) durchlassen, damit ein Admin
        // sich anmelden und die Wartung beenden kann.
        $user = $request->user();
        if ($user === null || $this->isAllowedRoute($request)) {
            return $next($request);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        return $this->deny($request, $org);
    }

    private function denyWindow(Request $request, \App\Models\MaintenanceWindow $window): Response {
        $message = trim((string) $window->message);
        if ($message === '') {
            $message = $window->read_only
                ? __('maintenance.window.read_only_message')
                : __('Dieser Bereich wird gerade gewartet. Bitte versuchen Sie es später erneut.');
        }
        $retryAfter = max(60, (int) now()->diffInSeconds($window->ends_at, false));

        if ($request->expectsJson()) {
            return new JsonResponse([
                'error' => 'maintenance',
                'message' => $message,
            ], Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => (string) $retryAfter]);
        }

        return response()
            ->view('errors.maintenance', ['message' => $message, 'until' => $window->ends_at], Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Retry-After', (string) $retryAfter);
    }

    private function currentOrganization(): ?Organization {
        if (! app()->bound('currentOrganization')) {
            return null;
        }

        $org = app('currentOrganization');

        return $org instanceof Organization ? $org : null;
    }

    private function isAllowedRoute(Request $request): bool {
        $name = (string) ($request->route()?->getName() ?? '');
        foreach (self::ALLOWED_ROUTE_PREFIXES as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function deny(Request $request, Organization $org): Response {
        $settings = $org->maintenanceSettings();
        $message = trim((string) ($settings['message'] ?? ''));
        if ($message === '') {
            $message = __('Dieser Bereich wird gerade gewartet. Bitte versuchen Sie es später erneut.');
        }

        $retryAfter = 3600;
        $until = $settings['until'] ?? null;
        if ($until instanceof \Carbon\CarbonInterface && $until->isFuture()) {
            $retryAfter = max(60, (int) now()->diffInSeconds($until, false));
        }

        if ($request->expectsJson()) {
            return new JsonResponse([
                'error' => 'maintenance',
                'message' => $message,
            ], Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => (string) $retryAfter]);
        }

        return response()
            ->view('errors.maintenance', ['message' => $message, 'until' => $until], Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Retry-After', (string) $retryAfter);
    }
}
