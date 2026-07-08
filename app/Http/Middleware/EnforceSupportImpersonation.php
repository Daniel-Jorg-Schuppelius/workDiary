<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnforceSupportImpersonation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\SupportImpersonationController;
use App\Models\{SupportAccessGrant, User};
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Überwacht laufende Support-Impersonationen (Rang 64):
 *
 *  - Ablauf/Widerruf der Freigabe beendet die Sitzung sofort (nächster
 *    Request), inkl. `support.impersonation.stop`-Audit.
 *  - Harte Sperrliste: keine Passwort-/2FA-Änderungen, keine API-Tokens,
 *    keine Exporte/Downloads — unabhängig vom Freigabe-Scope.
 *  - `read_only`-Freigaben lassen nur lesende Requests zu.
 *  - Jede schreibende Aktion wird als `support.session.action` auditiert.
 */
class EnforceSupportImpersonation {
    /** Immer erreichbar: Sitzung beenden / Abmelden. */
    private const ALLOWED_ROUTES = [
        'admin.support.impersonate.stop',
        'logout',
    ];

    /** Harte Sperren (Routenname enthält einen dieser Bausteine). */
    private const BLOCKED_ROUTE_PARTS = [
        'password',
        'two-factor',
        'webauthn',
        'api-tokens',
        'export',
        'download',
        'purge',
        'support.grants',
        'support.impersonate.start',
    ];

    public function handle(Request $request, Closure $next): Response {
        $state = $request->session()->get(SupportImpersonationController::SESSION_KEY);
        if (! is_array($state)) {
            return $next($request);
        }

        $grant = SupportAccessGrant::query()->withoutGlobalScopes()
            ->whereKey((int) ($state['grant_id'] ?? 0))->first();
        if (! $grant instanceof SupportAccessGrant || ! $grant->isActive()) {
            return $this->endSession($request, $state, $grant);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if (in_array($routeName, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        foreach (self::BLOCKED_ROUTE_PARTS as $part) {
            if (str_contains($routeName, $part)) {
                abort(403, __('Diese Aktion ist im Support-Modus gesperrt.'));
            }
        }

        $isRead = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
        if (! $isRead && $grant->scope === SupportAccessGrant::SCOPE_READ_ONLY) {
            abort(403, __('Die Supportfreigabe ist auf lesenden Zugriff beschränkt.'));
        }

        if (! $isRead) {
            // Nachvollziehbarkeit: jede schreibende Support-Aktion landet in
            // der Supportzugriffe-Audit-Sicht (support.%-Filter).
            $grant->audit('support.session.action', [
                'route' => $routeName !== '' ? $routeName : $request->path(),
                'method' => $request->method(),
                'target_user_id' => $request->user()?->id,
            ]);
        }

        return $next($request);
    }

    /**
     * Freigabe abgelaufen/widerrufen: zurück zum Support-Account (falls
     * möglich), Audit schreiben und mit Hinweis umleiten.
     *
     * @param array<string, mixed> $state
     */
    private function endSession(Request $request, array $state, ?SupportAccessGrant $grant): Response {
        $target = $request->user();
        $request->session()->forget(SupportImpersonationController::SESSION_KEY);

        $impersonator = User::query()->withoutGlobalScopes()
            ->whereKey((int) ($state['impersonator_id'] ?? 0))->first();
        if ($impersonator instanceof User && ! $impersonator->isDeactivated()) {
            Auth::guard('web')->login($impersonator);
        } else {
            Auth::guard('web')->logout();
        }
        $request->session()->regenerate();

        $grant?->audit('support.impersonation.stop', [
            'target_user_id' => $target?->id,
            'duration_seconds' => max(0, now()->getTimestamp() - (int) ($state['started_at'] ?? now()->getTimestamp())),
            'reason' => 'expired',
        ]);

        if (! Auth::guard('web')->check()) {
            return redirect()->route('login');
        }

        return redirect()->route('today.show')
            ->with('error', __('Die Supportfreigabe ist abgelaufen oder wurde widerrufen — die Support-Sitzung wurde beendet.'));
    }
}
