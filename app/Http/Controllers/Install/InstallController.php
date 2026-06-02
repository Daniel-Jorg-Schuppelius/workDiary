<?php
/*
 * Created on   : Mon Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InstallController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Services\Install\InstallationManager;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

/**
 * Geführter Web-Installer (Wizard). Jeder Schritt persistiert seine Werte
 * sofort (in die .env bzw. Datenbank), sodass ein Abbruch jederzeit gefahrlos
 * wiederholbar ist. Der Abschluss-Schritt setzt die Lock-Datei.
 */
class InstallController extends Controller {
    /** Reihenfolge der Wizard-Schritte für die Fortschrittsanzeige. */
    private const STEPS = ['requirements', 'application', 'database', 'admin', 'mail', 'integrations', 'finish'];

    public function __construct(private readonly InstallationManager $installer) {
    }

    // ── Schritt 1: Voraussetzungen ───────────────────────────────────────

    public function index(Request $request): View {
        $driver = (string) $request->query('driver', 'sqlite');
        if (! in_array($driver, InstallationManager::DRIVERS, true)) {
            $driver = 'sqlite';
        }

        return view('install.requirements', [
            'step' => 'requirements',
            'steps' => self::STEPS,
            'driver' => $driver,
            'checks' => $this->installer->requirements($driver),
            'met' => $this->installer->requirementsMet($driver),
        ]);
    }

    // ── Schritt 2: Anwendung & APP_KEY ───────────────────────────────────

    public function application(): View {
        $env = $this->installer->env();

        return view('install.application', [
            'step' => 'application',
            'steps' => self::STEPS,
            'values' => [
                'app_name' => $env->get('APP_NAME') ?? 'WorkDiary',
                'app_url' => $env->get('APP_URL') ?? 'http://localhost',
                'app_env' => $env->get('APP_ENV') ?? 'production',
                'locale' => $env->get('APP_LOCALE') ?? 'de',
                'timezone' => $env->get('APP_TIMEZONE') ?? 'Europe/Berlin',
            ],
            'hasAppKey' => $this->installer->hasAppKey(),
        ]);
    }

    public function storeApplication(Request $request): RedirectResponse {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
            'app_env' => ['required', 'in:local,production'],
            'locale' => ['required', 'string', 'max:8'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $this->installer->configureApp($data);

        return redirect()->route('install.database')
            ->with('success', __('Anwendungseinstellungen gespeichert & Anwendungsschlüssel sichergestellt.'));
    }

    // ── Schritt 3: Datenbank ─────────────────────────────────────────────

    public function database(): View {
        $env = $this->installer->env();

        return view('install.database', [
            'step' => 'database',
            'steps' => self::STEPS,
            'drivers' => InstallationManager::DRIVERS,
            'values' => [
                'driver' => $env->get('DB_CONNECTION') ?? 'sqlite',
                'host' => $env->get('DB_HOST') ?? '127.0.0.1',
                'port' => $env->get('DB_PORT') ?? '3306',
                'database' => $env->get('DB_DATABASE') ?? database_path('database.sqlite'),
                'username' => $env->get('DB_USERNAME') ?? '',
            ],
        ]);
    }

    public function storeDatabase(Request $request): RedirectResponse {
        $data = $request->validate([
            'driver' => ['required', 'in:' . implode(',', InstallationManager::DRIVERS)],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $config = [
            'driver' => $data['driver'],
            'host' => $data['host'] ?? '127.0.0.1',
            'port' => $data['port'] ?? null,
            'database' => $data['database'],
            'username' => $data['username'] ?? '',
            'password' => $data['password'] ?? '',
        ];

        if (! $this->installer->testConnection($config)) {
            return back()->withInput()->withErrors([
                'database' => __('Datenbankverbindung fehlgeschlagen. Bitte Zugangsdaten prüfen.'),
            ]);
        }

        try {
            $this->installer->configureDatabase($config);
            $this->installer->runMigrations();
            $this->installer->seedRolesAndPermissions();
        } catch (Throwable $e) {
            return back()->withInput()->withErrors([
                'database' => __('Migration fehlgeschlagen: :msg', ['msg' => $e->getMessage()]),
            ]);
        }

        return redirect()->route('install.admin')
            ->with('success', __('Datenbank konfiguriert, Migrationen ausgeführt.'));
    }

    // ── Schritt 4: Admin & Organisation ──────────────────────────────────

    public function admin(): View {
        return view('install.admin', [
            'step' => 'admin',
            'steps' => self::STEPS,
        ]);
    }

    public function storeAdmin(Request $request): RedirectResponse {
        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        try {
            $this->installer->createOrganizationAndAdmin($data);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors([
                'email' => __('Admin-Anlage fehlgeschlagen: :msg', ['msg' => $e->getMessage()]),
            ]);
        }

        return redirect()->route('install.mail')
            ->with('success', __('Organisation & Administrator angelegt.'));
    }

    // ── Schritt 5: Mail / SMTP ───────────────────────────────────────────

    public function mail(): View {
        $env = $this->installer->env();

        return view('install.mail', [
            'step' => 'mail',
            'steps' => self::STEPS,
            'values' => [
                'mailer' => $env->get('MAIL_MAILER') ?? 'log',
                'host' => $env->get('MAIL_HOST') ?? '127.0.0.1',
                'port' => $env->get('MAIL_PORT') ?? '2525',
                'username' => $env->get('MAIL_USERNAME') ?? '',
                'scheme' => $env->get('MAIL_SCHEME') ?? '',
                'from_address' => $env->get('MAIL_FROM_ADDRESS') ?? 'hello@example.com',
                'from_name' => $env->get('MAIL_FROM_NAME') ?? 'WorkDiary',
            ],
        ]);
    }

    public function storeMail(Request $request): RedirectResponse {
        $data = $request->validate([
            'mailer' => ['required', 'in:log,smtp'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'scheme' => ['nullable', 'in:tls,ssl'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        $this->installer->configureMail($data);

        return redirect()->route('install.integrations')
            ->with('success', __('E-Mail-Einstellungen gespeichert.'));
    }

    // ── Schritt 6: Integrationen (optional) ──────────────────────────────

    public function integrations(): View {
        $env = $this->installer->env();

        return view('install.integrations', [
            'step' => 'integrations',
            'steps' => self::STEPS,
            'values' => [
                'lexoffice_api_key' => $env->get('LEXOFFICE_API_KEY') ?? '',
                'vapid_public_key' => $env->get('VAPID_PUBLIC_KEY') ?? '',
                'vapid_private_key' => $env->get('VAPID_PRIVATE_KEY') ?? '',
                'vapid_subject' => $env->get('VAPID_SUBJECT') ?? 'mailto:admin@example.com',
            ],
        ]);
    }

    public function storeIntegrations(Request $request): RedirectResponse {
        $data = $request->validate([
            'lexoffice_api_key' => ['nullable', 'string', 'max:255'],
            'vapid_public_key' => ['nullable', 'string', 'max:255'],
            'vapid_private_key' => ['nullable', 'string', 'max:255'],
            'vapid_subject' => ['nullable', 'string', 'max:255'],
        ]);

        $this->installer->configureIntegrations($data);

        return redirect()->route('install.finish');
    }

    // ── Schritt 7: Abschluss ─────────────────────────────────────────────

    public function finish(): View {
        return view('install.finish', [
            'step' => 'finish',
            'steps' => self::STEPS,
        ]);
    }

    public function complete(): RedirectResponse {
        $this->installer->markInstalled();

        // Sicherheitshalber alle ausgeloggt in den frisch installierten
        // Zustand entlassen — der Admin meldet sich regulär neu an.
        Auth::logout();

        return redirect()->route('login')
            ->with('success', __('Installation abgeschlossen. Bitte melden Sie sich an.'));
    }
}
