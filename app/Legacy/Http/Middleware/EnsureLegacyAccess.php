<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnsureLegacyAccess.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Legacy\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Blockiert Zugriff auf den Legacy-Bereich für User ohne legacy_user_id
 * (und ohne Admin-Rolle).
 */
class EnsureLegacyAccess {
    public function handle(Request $request, Closure $next): Response {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessLegacy()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Kein Zugriff auf das Legacy-System.'),
                ], 403);
            }

            // Neu-only-User landen hier u. a. über alte Bookmarks. Statt 403
            // freundlich in den neuen Bereich umlenken, sofern verfügbar.
            if ($user instanceof User && $user->canAccessNew()) {
                $request->session()->put('work_mode', 'new');

                return redirect()->route('dashboard')
                    ->with('info', __('Sie wurden in das neue System geleitet.'));
            }

            throw new AccessDeniedHttpException(__('Kein Zugriff auf das Legacy-System.'));
        }

        // Harte Modus-Trennung: ist der User für beide Bereiche berechtigt,
        // aber gerade im neuen Modus, darf er KEINE Legacy-Seite per Direkt-URL
        // öffnen. Redirect statt 403.
        $workMode = $request->hasSession() ? $request->session()->get('work_mode') : null;

        if ($workMode === 'new' && $user->canAccessNew()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Sie sind aktuell im neuen Modus. Bitte wechseln Sie zuerst in den Legacy-Modus.'),
                    'target_mode' => 'legacy',
                ], 409);
            }

            return redirect()->route('dashboard')
                ->with('info', __('Sie sind im neuen Modus. Bitte zuerst in den Legacy-Modus wechseln, um diese Seite zu öffnen.'));
        }

        return $next($request);
    }
}
