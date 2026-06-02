<?php
/*
 * Created on   : Mon Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrepareInstaller.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Middleware;

use App\Services\Install\InstallationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Bereitet die Laufzeitumgebung für den Web-Installer vor und löst das
 * "Henne-Ei"-Problem eines frischen Clones:
 *
 * 1. Ohne APP_KEY würfen EncryptCookies/StartSession sofort eine
 *    MissingAppKeyException. Daher wird — solange die App nicht installiert
 *    ist — direkt beim ersten Request ein APP_KEY in der .env erzeugt
 *    (niemals ein vorhandener überschrieben) und in die Runtime geladen.
 * 2. SESSION_DRIVER/CACHE_STORE stehen per Default auf "database"; die DB ist
 *    während der Installation aber noch nicht konfiguriert. Deshalb werden
 *    Session und Cache für die Dauer des Wizards auf dateibasierte Treiber
 *    umgestellt, damit der mehrstufige Ablauf (CSRF, Flash-Daten) funktioniert.
 *
 * Muss als allererste Middleware der web-Gruppe laufen — noch vor
 * EncryptCookies und StartSession.
 */
class PrepareInstaller {
    public function __construct(private readonly InstallationManager $installer) {
    }

    public function handle(Request $request, Closure $next): Response {
        if ($this->installer->isInstalled()) {
            return $next($request);
        }

        try {
            $this->installer->ensureAppKey();
        } catch (Throwable) {
            // Best-effort: schlägt das Schreiben fehl (z. B. fehlende
            // Schreibrechte), zeigt der Voraussetzungs-Check den Grund an.
        }

        // Session/Cache während der Installation DB-unabhängig betreiben.
        config([
            'session.driver' => 'file',
            'session.encrypt' => false,
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);

        return $next($request);
    }
}
