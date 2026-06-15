<?php
/*
 * Created on   : Mon Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnforceTenantStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\{Organization, User};
use Closure;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sperrt schreibende Aktionen, wenn der SaaS-Mandantenstatus der aktuellen
 * Organisation den Schreibzugriff blockiert (suspended/expired, Feature 021).
 *
 * Lesezugriff bleibt erhalten (nur unsichere HTTP-Methoden werden geprüft).
 * Der Auth-/2FA-/Lizenz-Fluss bleibt unangetastet: ausgenommen sind nicht
 * authentifizierte Anfragen sowie die Lizenz-/Logout-/Mandantenstatus-Routen,
 * damit ein Plattform-Admin die Sperre wieder aufheben kann. HTML-Aufrufer
 * erhalten 423 (→ errors/423), API-Aufrufer ein JSON.
 */
class EnforceTenantStatus {
    /** Routen, die trotz Sperre erreichbar bleiben müssen (Aufhebung/Logout). */
    private const ALLOWED_ROUTE_PREFIXES = [
        'admin.license',
        'admin.tenants',
        'license.',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response {
        if ($this->isReadOnly($request)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $org = $user->organization;
        if (! $org instanceof Organization || ! $org->tenantWritesBlocked()) {
            return $next($request);
        }

        if ($this->isAllowedRoute($request)) {
            return $next($request);
        }

        return $this->deny($request, $org);
    }

    private function isReadOnly(Request $request): bool {
        return in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
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
        $status = $org->tenantStatus();
        $message = __('Dieser Mandant ist gesperrt (:status). Schreibende Aktionen sind deaktiviert. Bitte wenden Sie sich an den Betreiber.', [
            'status' => $status->label(),
        ]);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'error' => 'tenant_suspended',
                'status' => $status->value,
                'message' => $message,
            ], Response::HTTP_LOCKED);
        }

        throw new HttpException(Response::HTTP_LOCKED, $message);
    }
}
