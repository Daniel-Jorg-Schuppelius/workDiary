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

use App\Models\User;
use App\Services\Install\InstallationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sperrt die Installer-Routen, sobald die Anwendung installiert ist. Verhindert,
 * dass der Wizard nach Abschluss erneut Konfiguration oder Admin-Anlage erlaubt.
 *
 * **Der Marker allein reicht nicht** (Sicherheitsscan 2026-08-23, S-16):
 * `storage/installed` liegt außerhalb von `storage/app`, wird weder von
 * `scripts/backup.sh` noch vom Snapshot gesichert und ist in der
 * Restore-Anleitung nicht erwähnt. Nach einem dokumentierten
 * Disaster-Recovery stand der Wizard also gegen eine **vollständige**
 * Mandanten-Datenbank offen — inklusive Anlage eines neuen
 * Plattform-Betreibers und Umbiegen der Datenbankverbindung.
 *
 * Deshalb gilt zusätzlich: existiert bereits ein Plattform-Betreiber, ist die
 * Installation gelaufen. Die Ausnahme ist die Sitzung, die den Wizard selbst
 * gestartet hat — sonst könnte sie ihre eigenen Folgeschritte (Mail,
 * Integrationen, Abschluss) nicht mehr erreichen, nachdem sie den Betreiber
 * angelegt hat.
 */
class RedirectIfInstalled {
    /** Session-Schlüssel des laufenden Wizard-Durchgangs. */
    public const WIZARD_SESSION_KEY = 'install.wizard';

    public function __construct(private readonly InstallationManager $installer) {}

    public function handle(Request $request, Closure $next): Response {
        if ($this->installer->isInstalled()) {
            abort(404);
        }

        if ($this->platformAdminExists() && ! $request->session()->get(self::WIZARD_SESSION_KEY, false)) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * Gibt es schon einen Plattform-Betreiber?
     *
     * Bewusst fehlertolerant: im ersten Wizard-Schritt existiert noch gar
     * keine erreichbare Datenbank — eine Abfrage darf die Installation nicht
     * verhindern.
     */
    private function platformAdminExists(): bool {
        try {
            return User::query()->withoutGlobalScopes()->where('is_platform_admin', true)->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
