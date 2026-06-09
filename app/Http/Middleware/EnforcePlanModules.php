<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnforcePlanModules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Licensing\FeatureFlagResolver;
use Closure;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Hartes Modul-Gating: sperrt Routen, deren Modul der aktuelle Plan/Lizenz
 * nicht enthaelt. Zentrale Map in config('plans.routes') (Route-Namen-Muster →
 * Modul-Code). Nicht gelistete Routen gelten als Core. HTML-Aufrufer erhalten
 * 423 (→ errors/423-Upsell), API-Aufrufer ein JSON.
 */
class EnforcePlanModules {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function handle(Request $request, Closure $next): Response {
        $module = $this->features->moduleForRoute($request->route()?->getName());

        if ($module !== null && ! $this->features->isEnabled($module)) {
            return $this->deny($request, $module);
        }

        return $next($request);
    }

    private function deny(Request $request, string $module): Response {
        $label = (string) (config('plans.labels')[$module] ?? $module);
        $message = __('Das Modul „:modul" ist in Ihrem aktuellen Plan nicht enthalten.', ['modul' => $label]);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'error' => 'module_unavailable',
                'module' => $module,
                'message' => $message,
            ], Response::HTTP_LOCKED);
        }

        throw new HttpException(Response::HTTP_LOCKED, $message);
    }
}
