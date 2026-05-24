<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnsureValidLicense.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Middleware;

use App\Services\Licensing\{LicenseService, LicenseStatus};
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidLicense {
    public function __construct(private readonly LicenseService $service) {}

    public function handle(Request $request, Closure $next): Response {
        if (! $this->service->isEnforced()) {
            return $next($request);
        }

        if ($this->isDevHost($request->getHost())) {
            return $next($request);
        }

        $result = $this->service->current($request->getHost());

        // Tampering darf nicht über Bypass-Pfade umgangen werden — sonst
        // bleiben /login & Co. trotz manipulierter Lizenzdateien offen.
        if ($result->status !== LicenseStatus::Tampered) {
            foreach ((array) config('license.bypass_paths', []) as $pattern) {
                if ($request->is($pattern)) {
                    return $next($request);
                }
            }
        }

        if ($result->isUsable()) {
            if ($result->status === LicenseStatus::GracePeriod && $result->payload !== null) {
                app()->instance('licenseGraceUntil', $result->payload->expiresAt?->addDays((int) config('license.grace_days', 14)));
            }

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'License required.',
                'status' => $result->status->value,
            ], 402);
        }

        return response()->view('licensing.required', [
            'status' => $result->status,
            'message' => $result->message,
            'host' => $request->getHost(),
        ], 402);
    }

    private function isDevHost(string $host): bool {
        $allowedEnvs = (array) config('license.dev_host_envs', []);
        if ($allowedEnvs !== [] && ! in_array((string) app()->environment(), $allowedEnvs, true)) {
            return false;
        }

        $host = strtolower($host);

        foreach ((array) config('license.dev_hosts', []) as $pattern) {
            $pattern = strtolower((string) $pattern);
            if ($pattern === '') {
                continue;
            }
            if ($pattern === $host) {
                return true;
            }
            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
                if (preg_match($regex, $host) === 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
