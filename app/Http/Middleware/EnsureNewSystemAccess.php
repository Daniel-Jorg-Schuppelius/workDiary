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

            // Legacy-only-User landen hier u. a. nach einem Klick auf einen
            // Deep-Link ins neue System. Statt sie mit einer 403-Seite zu
            // begrüßen, schalten wir den Modus zurück auf Legacy und leiten
            // sie zur Legacy-Startseite. Das entspricht ihrem zulässigen
            // Funktionsumfang ohne erklärungsbedürftigen Fehlerdialog.
            $legacyConfigured = filled(config('database.connections.legacy.database'));
            if ($user instanceof User && $user->canAccessLegacy() && $legacyConfigured) {
                $request->session()->put('work_mode', 'legacy');

                return redirect()->route('legacy.diary.index')
                    ->with('info', __('Sie wurden in das Legacy-System geleitet.'));
            }

            throw new AccessDeniedHttpException(__('Kein Zugriff auf das neue System.'));
        }

        // Harte Modus-Trennung: ist der User berechtigt für beide Bereiche,
        // aber gerade im Legacy-Modus, darf er KEINE neue-Bereich-Seite per
        // Direkt-URL öffnen. Die Trennung greift rein anhand des Modus.
        // Default ist 'legacy' – identisch zu Layout (app.blade.php) und
        // HomeController, damit ein noch nicht gesetzter work_mode nicht
        // versehentlich als "neuer Modus" interpretiert wird.
        $legacyConfigured = filled(config('database.connections.legacy.database'));
        // Fehlt der Session-Modus (frische Session nach Ablauf/F5), greift die
        // persistierte Modus-Wahl des Users statt hart 'legacy' – sonst landen
        // Dual-Access-User (Admins) ungewollt wieder im Legacy-Modus. Der Wert
        // wird in die Session zurückgeschrieben, damit Layout-Chrome (Body-Mode)
        // konsistent bleibt.
        if ($request->hasSession()) {
            if (! $request->session()->has('work_mode')) {
                $request->session()->put('work_mode', $user->preferredWorkMode());
            }
            $workMode = $request->session()->get('work_mode');
        } else {
            $workMode = null;
        }

        if ($workMode === 'legacy' && $user->canAccessLegacy()) {
            // Ohne konfigurierte Legacy-DB existiert kein Legacy-Bereich, in
            // den wir umleiten könnten – der Legacy-Modus ist dann ein
            // ungültiger Zustand. Wir normalisieren ihn auf "new" und lassen
            // den Request durch (ein Redirect würde hier endlos schleifen,
            // da die Startseite new-fähige User wieder ins neue System leitet).
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
