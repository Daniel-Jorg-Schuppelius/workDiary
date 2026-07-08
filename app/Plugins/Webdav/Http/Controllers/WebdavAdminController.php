<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Webdav\Http\Controllers;

use App\Enums\Document\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\{Organization, User, WebdavConnection};
use App\Plugins\Webdav\Contracts\WebdavGatewayFactory;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Artisan, Auth};
use Illuminate\View\View;
use Throwable;

/**
 * WebDAV-Admin-Panel (Feature 058, MVP-127): eine Ablage je Organisation
 * (Collection-URL, Zugangsdaten verschlüsselt, Ordnerregeln Dokumenttyp→Ordner),
 * manueller Voll-Spiegellauf und Trennen. Das App-Passwort erscheint nie in
 * Views oder Audit-Payloads ({@see WebdavConnection::$hidden}); ein leeres
 * Passwortfeld lässt das bestehende Passwort unangetastet.
 */
class WebdavAdminController extends Controller {
    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = WebdavConnection::query()->where('organization_id', $organization->id)->first();

        return view('webdav::admin.index', [
            'connection' => $connection,
            'documentTypes' => DocumentType::cases(),
            'health' => $connection instanceof WebdavConnection && $connection->isActive()
                ? $this->probe($connection)
                : null,
        ]);
    }

    /** Legt die Ablage an oder aktualisiert sie (Passwort nur bei Eingabe). */
    public function store(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:190'],
            'app_password' => ['nullable', 'string', 'max:255'],
            'default_folder' => ['required', 'string', 'max:190'],
            'active' => ['nullable', 'boolean'],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['in:' . implode(',', WebdavConnection::SOURCES)],
            'folder_type' => ['array'],
            'folder_type.*' => ['nullable', 'string', 'max:32'],
            'folder_path' => ['array'],
            'folder_path.*' => ['nullable', 'string', 'max:190'],
        ]);

        $baseUrl = trim((string) $data['base_url']);
        if (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            return back()->with('error', __('webdav.flash.invalid_url'))->withInput();
        }

        /** @var WebdavConnection $connection */
        $connection = WebdavConnection::query()->firstOrNew(['organization_id' => $organization->id]);

        $attributes = [
            'name' => (string) $data['name'],
            'base_url' => rtrim($baseUrl, '/'),
            'username' => (string) $data['username'],
            'default_folder' => trim((string) $data['default_folder'], '/'),
            'folder_map' => $this->buildFolderMap($request),
            // Nur bekannte Quellen; leer = nur document (Default via Model).
            'sources' => array_values(array_intersect(WebdavConnection::SOURCES, (array) ($data['sources'] ?? []))),
            'active' => (bool) ($data['active'] ?? false),
            'created_by' => $connection->exists ? $connection->created_by : $admin->id,
        ];

        $password = trim((string) ($data['app_password'] ?? ''));
        if ($password !== '') {
            $attributes['app_password'] = $password;
        } elseif (! $connection->exists) {
            return back()->with('error', __('webdav.flash.password_required'))->withInput();
        }

        $connection->forceFill($attributes)->save();
        $connection->audit('webdav.connection_saved', ['by_user_id' => (int) $admin->id, 'active' => $connection->active]);

        return back()->with('success', __('webdav.flash.saved'));
    }

    /** Manueller Voll-Spiegellauf (auditiert). */
    public function mirror(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = WebdavConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof WebdavConnection || ! $connection->isActive()) {
            return back()->with('error', __('webdav.flash.no_connection'));
        }

        Artisan::call('webdav:mirror', ['--organization' => (string) $organization->id]);
        $connection->audit('webdav.mirror_manual', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('webdav.flash.mirror_done'));
    }

    /** Deaktiviert die Ablage; bereits gespiegelte Dateien bleiben extern erhalten. */
    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = WebdavConnection::query()->where('organization_id', $organization->id)->first();
        if ($connection instanceof WebdavConnection) {
            $connection->forceFill(['active' => false])->save();
            $connection->audit('webdav.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return back()->with('success', __('webdav.flash.disconnected'));
    }

    /**
     * Baut die Dokumenttyp→Ordner-Map aus paarigen Formularzeilen (nur gültige Typen).
     *
     * @return array<string, string>
     */
    private function buildFolderMap(Request $request): array {
        $types = (array) $request->input('folder_type', []);
        $paths = (array) $request->input('folder_path', []);
        $valid = array_map(static fn (DocumentType $t): string => $t->value, DocumentType::cases());

        $map = [];
        foreach ($types as $i => $type) {
            $type = is_string($type) ? $type : '';
            $path = isset($paths[$i]) && is_string($paths[$i]) ? trim($paths[$i], '/') : '';
            if ($type !== '' && $path !== '' && in_array($type, $valid, true)) {
                $map[$type] = $path;
            }
        }

        return $map;
    }

    /** @return array{ok: bool} */
    private function probe(WebdavConnection $connection): array {
        try {
            return ['ok' => app(WebdavGatewayFactory::class)->for($connection)->ping()];
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
