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
use App\Models\{CalDavConnection, Organization, User};
use App\Plugins\CalDav\Contracts\CalDavGatewayFactory;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Artisan, Auth};
use Illuminate\View\View;
use Throwable;

/**
 * CalDAV-Admin-Panel (Feature 058, MVP-126): eine Anbindung je Organisation
 * (Basis-URL, Zugangsdaten verschlüsselt, Ziel-Collection), manuelles Publish
 * und Trennen. Das App-Passwort erscheint nie in Views oder Audit-Payloads
 * ({@see CalDavConnection::$hidden}); ein leeres Passwortfeld beim Speichern
 * lässt das bestehende Passwort unangetastet.
 */
class CalDavAdminController extends Controller {
    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CalDavConnection::query()->where('organization_id', $organization->id)->first();

        return view('caldav::admin.index', [
            'connection' => $connection,
            'health' => $connection instanceof CalDavConnection && $connection->isActive()
                ? $this->probe($connection)
                : null,
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
        ]);

        $baseUrl = trim((string) $data['base_url']);
        if (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            return back()->with('error', __('caldav.flash.invalid_url'))->withInput();
        }

        /** @var CalDavConnection $connection */
        $connection = CalDavConnection::query()->firstOrNew(['organization_id' => $organization->id]);

        $attributes = [
            'name' => (string) $data['name'],
            'base_url' => rtrim($baseUrl, '/'),
            'username' => (string) $data['username'],
            'calendar_path' => trim((string) $data['calendar_path'], '/'),
            // Nur bekannte Scopes übernehmen; leer = nur Termine (Default via Model).
            'scopes' => array_values(array_intersect(CalDavConnection::SCOPES, (array) ($data['scopes'] ?? []))),
            'active' => (bool) ($data['active'] ?? false),
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

        Artisan::call('caldav:publish', ['--organization' => (string) $organization->id]);
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

    /** @return array{ok: bool} */
    private function probe(CalDavConnection $connection): array {
        try {
            return ['ok' => app(CalDavGatewayFactory::class)->for($connection)->ping()];
        } catch (Throwable) {
            return ['ok' => false];
        }
    }

    private function admin(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
