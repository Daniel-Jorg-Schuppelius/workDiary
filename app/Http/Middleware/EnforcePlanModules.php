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

use App\Enums\Licensing\ModuleStatus;
use App\Models\Organization;
use App\Services\Licensing\{FeatureFlagResolver, ModuleStatusResolver};
use Closure;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Hartes Modul-Gating: sperrt Routen, deren Modul der aktuelle Plan/Lizenz
 * nicht enthaelt ODER die Organisation lokal deaktiviert hat (MVP-052). Die
 * Map liegt in config('plans.routes'). Nicht gelistete Routen gelten als Core.
 * HTML-Aufrufer erhalten 423 (→ errors/423-Upsell), API-Aufrufer ein JSON.
 * Die Meldung unterscheidet „nicht lizenziert" von „org-deaktiviert".
 */
class EnforcePlanModules {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly ModuleStatusResolver $moduleStatus,
    ) {}

    public function handle(Request $request, Closure $next): Response {
        $module = $this->features->moduleForRoute($request->route()?->getName());

        if ($module !== null && ! $this->features->isEnabled($module)) {
            return $this->deny($request, $module, $this->statusFor($request, $module));
        }

        return $next($request);
    }

    private function statusFor(Request $request, string $module): ?ModuleStatus {
        $org = $this->currentOrganization($request);

        return $org !== null ? $this->moduleStatus->statusFor($org, $module) : null;
    }

    private function currentOrganization(Request $request): ?Organization {
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                return $org;
            }
        }

        $org = $request->user()?->organization;

        return $org instanceof Organization ? $org : null;
    }

    private function deny(Request $request, string $module, ?ModuleStatus $status): Response {
        $label = (string) (config('plans.labels')[$module] ?? $module);

        // MVP-052 Akzeptanz 4: unterscheidbare deutsche Meldung.
        if ($status === ModuleStatus::InactiveByCustomer) {
            $message = __('Das Modul „:modul" wurde von Ihrer Organisation deaktiviert.', ['modul' => $label]);
            $reason = 'module_disabled_by_organization';
        } else {
            $message = __('Das Modul „:modul" ist in Ihrem aktuellen Plan nicht enthalten.', ['modul' => $label]);
            $reason = 'module_unavailable';
        }

        if ($request->expectsJson()) {
            return new JsonResponse([
                'error' => $reason,
                'module' => $module,
                'message' => $message,
            ], Response::HTTP_LOCKED);
        }

        throw new HttpException(Response::HTTP_LOCKED, $message);
    }
}
