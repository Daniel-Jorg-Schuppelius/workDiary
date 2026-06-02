<?php
/*
 * Created on   : Mon Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedirectIfNotInstalled.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Middleware;

use App\Services\Install\InstallationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leitet jede Anfrage auf den Web-Installer um, solange die Anwendung nicht
 * als installiert markiert ist (Lock-Datei storage/installed fehlt).
 *
 * Ausgenommen sind die Installer-Routen selbst sowie technische Pfade
 * (Health-Check, gebaute Assets), damit der Wizard überhaupt bedienbar ist.
 */
class RedirectIfNotInstalled {
    public function __construct(private readonly InstallationManager $installer) {
    }

    public function handle(Request $request, Closure $next): Response {
        if ($this->installer->isInstalled()) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Application not installed.',
            ], 503);
        }

        return redirect()->route('install.index');
    }

    private function isExempt(Request $request): bool {
        return $request->is('install', 'install/*', 'up', 'build/*', 'storage/*', 'favicon.ico');
    }
}
