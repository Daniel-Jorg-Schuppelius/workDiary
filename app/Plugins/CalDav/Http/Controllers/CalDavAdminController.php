<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\CalDav\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{CalDavConnection, PluginState};
use App\Plugins\CalDav\CalDavPlugin;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * CalDAV-Admin-Panel (Feature 058, MVP-126): eine Anbindung je Organisation
 * (Basis-URL, Zugangsdaten verschlüsselt, Ziel-Collection), manuelles Publish
 * und Trennen. Das App-Passwort erscheint nie in Views oder Audit-Payloads
 * ({@see CalDavConnection::$hidden}); ein leeres Passwortfeld beim Speichern
 * lässt das bestehende Passwort unangetastet.
 */
class CalDavAdminController extends Controller {
    use ResolvesPluginOrgContext;

    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CalDavConnection::query()->where('organization_id', $organization->id)->first();

        return view('caldav::admin.index', [
            'connection' => $connection,
            // Gespeicherter Stand statt Ping beim Seitenaufruf (UI-Fuzz 2026-09-21).
            'healthState' => PluginState::forContext(CalDavPlugin::ID, $organization->id),
        ]);
    }

    /** Legt die Anbindung an oder aktualisiert sie (Passwort nur bei Eingabe). */
    public function store(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:190'],
            'app_password' => ['nullable', 'string', 'max:255'],
            'calendar_path' => ['required', 'string', 'max:255'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['in:' . implode(',', CalDavConnection::SCOPES)],
            'active' => ['nullable', 'boolean'],
            'two_way' => ['nullable', 'boolean'],
        ]);

        $baseUrl = trim((string) $data['base_url']);
        if (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            return back()->with('error', __('caldav.flash.invalid_url'))->withInput();
        }

        // Nextclouds „Link kopieren" liefert die volle Kalender-URL; hinter die Basis-URL
        // gehängt ergab sie https://…/https://… (UI-Fuzz 2026-09-21).
        $calendarPath = trim((string) $data['calendar_path']);
        if (preg_match('#^https?://#i', $calendarPath) === 1) {
            $prefix = rtrim($baseUrl, '/') . '/';
            if (! str_starts_with(strtolower($calendarPath), strtolower($prefix))) {
                return back()->withErrors(['calendar_path' => __('caldav.flash.path_outside_base')])->withInput();
            }
            $calendarPath = substr($calendarPath, strlen($prefix));
        }

        /** @var CalDavConnection $connection */
        $connection = CalDavConnection::query()->firstOrNew(['organization_id' => $organization->id]);

        $attributes = [
            'name' => (string) $data['name'],
            'base_url' => rtrim($baseUrl, '/'),
            'username' => (string) $data['username'],
            'calendar_path' => trim($calendarPath, '/'),
            // Nur bekannte Scopes übernehmen; leer = nur Termine (Default via Model).
            'scopes' => array_values(array_intersect(CalDavConnection::SCOPES, (array) ($data['scopes'] ?? []))),
            'active' => (bool) ($data['active'] ?? false),
            'two_way' => (bool) ($data['two_way'] ?? false),
            'created_by' => $connection->exists ? $connection->created_by : $admin->id,
        ];

        // Passwort nur bei Eingabe setzen — nie leere Strings in encrypted-Felder.
        $password = trim((string) ($data['app_password'] ?? ''));
        if ($password !== '') {
            $attributes['app_password'] = $password;
        } elseif (! $connection->exists) {
            return back()->with('error', __('caldav.flash.password_required'))->withInput();
        }

        $connection->forceFill($attributes)->save();
        $connection->audit('caldav.connection_saved', ['by_user_id' => (int) $admin->id, 'active' => $connection->active]);

        return back()->with('success', __('caldav.flash.saved'));
    }

    /** Manuelles Publish (Scheduler-Äquivalent, auditiert). */
    public function publish(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CalDavConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof CalDavConnection || ! $connection->isActive()) {
            return back()->with('error', __('caldav.flash.no_connection'));
        }

        // Queue statt Request (Vollscan 2026-08-23, J17): ein Voll-Sync im Web-
        // Request lief in den PHP-Timeout; der Worker hat Retry und Laufzeitbudget.
        Artisan::queue('caldav:publish', ['--organization' => (string) $organization->id]);
        $connection->audit('caldav.publish_manual', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('caldav.flash.publish_done'));
    }

    /** Deaktiviert die Anbindung; publizierte Termine bleiben extern erhalten. */
    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CalDavConnection::query()->where('organization_id', $organization->id)->first();
        if ($connection instanceof CalDavConnection) {
            $connection->forceFill(['active' => false])->save();
            $connection->audit('caldav.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return back()->with('success', __('caldav.flash.disconnected'));
    }
}
