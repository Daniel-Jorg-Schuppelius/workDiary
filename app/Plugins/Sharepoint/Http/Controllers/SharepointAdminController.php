<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Sharepoint\Http\Controllers;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use App\Enums\Document\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\{Organization, SharepointConnection, User};
use App\Plugins\Sharepoint\Api\{SharepointDriveClient, SharepointOAuth};
use App\Plugins\Sharepoint\SharepointConfig;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Artisan, Auth, Cache};
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * SharePoint-Admin-Panel + OAuth-Verbindungsflow (MVP-330, Bauturbo A10).
 * Der OAuth-`state` ist kurzlebig, einmalig einlösbar und an Organisation UND
 * Sitzung gebunden (A8-/Todoist-Muster); der PKCE-Verifier wandert mit dem
 * state durch den Cache. Tokens erscheinen nie in Logs, Fehlermeldungen oder
 * Audit-Payloads. Site + Bibliothek werden serverseitig über Graph validiert
 * (kein Unterschieben fremder IDs); Ordnerregeln wie die WebDAV-Ablage.
 */
class SharepointAdminController extends Controller {
    private const STATE_TTL_SECONDS = 600;

    public function index(Request $request): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = SharepointConnection::query()->where('organization_id', $organization->id)->first();

        // Site-Suche + Bibliotheken (nur mit verbundenem Token; Fehler → leer,
        // der Health-Status meldet API-Probleme).
        $siteSearch = trim((string) $request->query('site_search', ''));
        $selectedSiteId = trim((string) $request->query('site_id', (string) ($connection->site_id ?? '')));
        $sites = [];
        $drives = [];
        $health = null;
        if ($connection instanceof SharepointConnection && trim((string) $connection->access_token) !== '') {
            $client = new SharepointDriveClient($connection);
            if ($siteSearch !== '') {
                try {
                    $sites = $client->listSites($siteSearch);
                } catch (Throwable) {
                    $sites = [];
                }
            }
            if ($selectedSiteId !== '') {
                try {
                    $drives = $client->listDrives($selectedSiteId);
                } catch (Throwable) {
                    $drives = [];
                }
            }
            if ($connection->isActive()) {
                try {
                    $health = ['ok' => $client->ping()];
                } catch (Throwable) {
                    $health = ['ok' => false];
                }
            }
        }

        return view('sharepoint::admin.index', [
            'configured' => SharepointConfig::isConfigured(),
            'connection' => $connection,
            'documentTypes' => DocumentType::cases(),
            'siteSearch' => $siteSearch,
            'selectedSiteId' => $selectedSiteId,
            'sites' => $sites,
            'drives' => $drives,
            'health' => $health,
        ]);
    }

    /** Startet den OAuth-Flow: org- und sitzungsgebundener Einmal-state + PKCE. */
    public function startOAuth(SharepointOAuth $oauth): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        if (! SharepointConfig::isConfigured()) {
            return back()->with('error', __('sharepoint.flash.not_configured'));
        }

        $state = Str::random(40);
        $verifier = OAuth2AuthorizationCodeGrant::generatePkceVerifier();
        Cache::put($this->stateKey($state), [
            'organization_id' => (int) $organization->id,
            'user_id' => (int) $admin->id,
            'pkce_verifier' => $verifier,
        ], self::STATE_TTL_SECONDS);

        $url = $oauth->grant()->getAuthorizationUrl($state, $oauth->scopes(), pkceVerifier: $verifier);

        return redirect()->away($url);
    }

    /** OAuth-Callback: state prüfen (einmalig!), Code + PKCE tauschen, Verbindung speichern. */
    public function oauthCallback(Request $request, SharepointOAuth $oauth): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        // Einmalig einlösen: pull() entfernt den state sofort (Replay-Schutz).
        $payload = $state !== '' ? Cache::pull($this->stateKey($state)) : null;
        if (! is_array($payload)
            || (int) ($payload['organization_id'] ?? 0) !== (int) $organization->id
            || (int) ($payload['user_id'] ?? 0) !== (int) $admin->id) {
            return redirect()->route('admin.sharepoint.index')->with('error', __('sharepoint.flash.state_invalid'));
        }

        if ($code === '') {
            return redirect()->route('admin.sharepoint.index')->with('error', __('sharepoint.flash.oauth_denied'));
        }

        try {
            $token = $oauth->grant()->exchangeAuthorizationCode($code, (string) ($payload['pkce_verifier'] ?? '') ?: null);
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Payload/Token.
            return redirect()->route('admin.sharepoint.index')
                ->with('error', __('sharepoint.flash.oauth_failed', ['class' => class_basename($e)]));
        }

        /** @var SharepointConnection $connection */
        $connection = SharepointConnection::query()->firstOrNew(['organization_id' => $organization->id]);
        $connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken(),
            'token_expires_at' => $token->getExpiresAt(),
            'scopes' => $token->getScope() ?? implode(' ', $oauth->scopes()),
            'status' => SharepointConnection::STATUS_ACTIVE,
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
            'connected_by' => $admin->id,
            'connected_at' => now(),
            'disconnected_by' => null,
            'disconnected_at' => null,
        ])->save();

        $connection->audit('sharepoint.connected', ['by_user_id' => (int) $admin->id]);

        return redirect()->route('admin.sharepoint.index')->with('success', __('sharepoint.flash.connected'));
    }

    /** Wählt Site + Dokumentbibliothek (beides serverseitig über Graph validiert). */
    public function selectTarget(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = SharepointConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof SharepointConnection || trim((string) $connection->access_token) === '') {
            return back()->with('error', __('sharepoint.flash.no_connection'));
        }

        $data = $request->validate([
            'site_id' => ['required', 'string', 'max:512'],
            'drive_id' => ['required', 'string', 'max:512'],
        ]);

        $client = new SharepointDriveClient($connection);
        $site = $client->getSite((string) $data['site_id']);
        if ($site === null) {
            return back()->with('error', __('sharepoint.flash.site_invalid'));
        }

        try {
            $drive = collect($client->listDrives($site['id']))->firstWhere('id', (string) $data['drive_id']);
        } catch (Throwable) {
            $drive = null;
        }
        if (! is_array($drive)) {
            return back()->with('error', __('sharepoint.flash.drive_invalid'));
        }

        $connection->forceFill([
            'site_id' => $site['id'],
            'site_name' => $site['name'],
            'drive_id' => $drive['id'],
            'drive_name' => (string) $drive['name'],
        ])->save();
        $connection->audit('sharepoint.target_selected', ['site_name' => $site['name'], 'drive_name' => (string) $drive['name']]);

        return redirect()->route('admin.sharepoint.index')->with('success', __('sharepoint.flash.target_saved'));
    }

    /** Speichert Ordnerregeln/Quellen/Aktiv-Schalter (WebDAV-Muster). */
    public function storeSettings(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = SharepointConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof SharepointConnection) {
            return back()->with('error', __('sharepoint.flash.no_connection'));
        }

        $data = $request->validate([
            'default_folder' => ['required', 'string', 'max:190'],
            'active' => ['nullable', 'boolean'],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['in:' . implode(',', SharepointConnection::SOURCES)],
            'folder_type' => ['array'],
            'folder_type.*' => ['nullable', 'string', 'max:32'],
            'folder_path' => ['array'],
            'folder_path.*' => ['nullable', 'string', 'max:190'],
        ]);

        $connection->forceFill([
            'default_folder' => trim((string) $data['default_folder'], '/'),
            'folder_map' => $this->buildFolderMap($request),
            // Nur bekannte Quellen; leer = nur document (Default via Trait).
            'sources' => array_values(array_intersect(SharepointConnection::SOURCES, (array) ($data['sources'] ?? []))),
            'active' => (bool) ($data['active'] ?? false),
        ])->save();
        $connection->audit('sharepoint.settings_saved', ['by_user_id' => (int) $admin->id, 'active' => $connection->active]);

        return back()->with('success', __('sharepoint.flash.saved'));
    }

    /** Manueller Voll-Spiegellauf (auditiert). */
    public function mirror(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = SharepointConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof SharepointConnection || ! $connection->isActive()) {
            return back()->with('error', __('sharepoint.flash.no_connection'));
        }

        Artisan::call('sharepoint:mirror', ['--organization' => (string) $organization->id]);
        $connection->audit('sharepoint.mirror_manual', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('sharepoint.flash.mirror_done'));
    }

    /** Trennt die Verbindung (auditiert); gespiegelte Dateien bleiben extern erhalten. */
    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = SharepointConnection::query()->where('organization_id', $organization->id)->first();
        if ($connection instanceof SharepointConnection) {
            $connection->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'status' => SharepointConnection::STATUS_DISCONNECTED,
                'disconnected_by' => $admin->id,
                'disconnected_at' => now(),
            ])->save();
            $connection->audit('sharepoint.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return redirect()->route('admin.sharepoint.index')->with('success', __('sharepoint.flash.disconnected'));
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

    private function stateKey(string $state): string {
        return 'sharepoint-oauth-state:' . $state;
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
