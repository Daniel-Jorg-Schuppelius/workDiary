<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnforcePlatformAdminIpAllowlist.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Security\SecurityEventType;
use App\Models\User;
use App\Services\Security\SecurityEventLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\{IpUtils, Response};

/**
 * Optionale IP-Allowlist für den Plattform-Adminbereich (Feature 096,
 * MVP-446): wirkt NUR auf Plattform-Admins in admin.*-Routen — Org-Admins
 * bleiben unberührt (Aussperr-Risiko begrenzen). Leer konfiguriert = aus.
 * Voraussetzung im Proxy-Betrieb: TRUSTED_PROXIES, sonst prüft die Liste
 * die Proxy-IP.
 */
class EnforcePlatformAdminIpAllowlist {
    public function handle(Request $request, Closure $next): Response {
        $list = trim((string) config('security.platform_admin_ip_allowlist', ''));
        if ($list === '') {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User || ! $user->isGlobalAdmin()) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if (! str_starts_with($routeName, 'admin.')) {
            return $next($request);
        }

        $allowed = array_values(array_filter(array_map('trim', explode(',', $list))));
        if (IpUtils::checkIp((string) $request->ip(), $allowed)) {
            return $next($request);
        }

        app(SecurityEventLogger::class)->log(SecurityEventType::PlatformAdminIpBlocked, [
            'user' => $user->email,
            'route' => $routeName,
        ]);

        abort(403, __('Zugriff auf den Plattform-Adminbereich ist von dieser IP nicht erlaubt.'));
    }
}
