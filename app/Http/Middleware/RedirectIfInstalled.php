<?php
/*
 * Created on   : Mon Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedirectIfInstalled.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Middleware;

use App\Services\Install\InstallationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sperrt die Installer-Routen, sobald die Anwendung installiert ist. Verhindert,
 * dass der Wizard nach Abschluss erneut Konfiguration oder Admin-Anlage erlaubt.
 */
class RedirectIfInstalled {
    public function __construct(private readonly InstallationManager $installer) {
    }

    public function handle(Request $request, Closure $next): Response {
        if ($this->installer->isInstalled()) {
            abort(404);
        }

        return $next($request);
    }
}
