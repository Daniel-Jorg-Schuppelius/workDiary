<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnsureNewSystemAccess.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Blockiert Zugriff auf Funktionen des neuen Systems für User, die dort
 * nicht freigeschaltet sind (kein is_new_system und kein Admin).
 */
class EnsureNewSystemAccess {
    public function handle(Request $request, Closure $next): Response {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessNew()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Kein Zugriff auf das neue System.'),
                ], 403);
            }

            // Legacy-only-User (z. B. via Deep-Link) statt 403 zurück in den Legacy-Modus leiten.
            $legacyConfigured = filled(config('database.connections.legacy.database'));
            if ($user instanceof User && $user->canAccessLegacy() && $legacyConfigured) {
                $request->session()->put('work_mode', 'legacy');

                return redirect()->route('legacy.diary.index')
                    ->with('info', __('Sie wurden in das Legacy-System geleitet.'));
            }

            throw new AccessDeniedHttpException(__('Kein Zugriff auf das neue System.'));
        }

        // Harte Modus-Trennung: im Legacy-Modus keine neue-Bereich-Seite per Direkt-URL.
        $legacyConfigured = filled(config('database.connections.legacy.database'));
        // Fehlt der Session-Modus, greift die persistierte Wahl (sonst landen Dual-Access-User ungewollt im Legacy-Modus).
        if ($request->hasSession()) {
            if (! $request->session()->has('work_mode')) {
                $request->session()->put('work_mode', $user->preferredWorkMode());
            }
            $workMode = $request->session()->get('work_mode');
        } else {
            $workMode = null;
        }

        if ($workMode === 'legacy' && $user->canAccessLegacy()) {
            // Ohne Legacy-DB ist der Legacy-Modus ungültig: auf "new" normalisieren und durchlassen (Redirect würde schleifen).
            if (! $legacyConfigured) {
                $request->session()->put('work_mode', 'new');

                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Sie sind aktuell im Legacy-Modus. Bitte wechseln Sie zuerst in den neuen Modus.'),
                    'target_mode' => 'new',
                ], 409);
            }

            return redirect()->route('legacy.diary.index')
                ->with('info', __('Sie sind im Legacy-Modus. Bitte zuerst in den neuen Modus wechseln, um diese Seite zu öffnen.'));
        }

        return $next($request);
    }
}
