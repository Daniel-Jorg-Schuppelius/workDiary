<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Zammad\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{Organization, Project, ZammadConnection};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Zammad\Contracts\ZammadGatewayFactory;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Throwable;

/**
 * Zammad-Admin-Panel (Feature 060, MVP-129): eine Anbindung je Organisation
 * (Basis-URL, Token verschlüsselt, Queue→Projekt-Zuordnung), manueller Import
 * und Trennen. Der Token erscheint nie in Views oder Audit-Payloads
 * ({@see ZammadConnection::$hidden}); ein leeres Token-Feld beim Speichern lässt
 * das bestehende Token unangetastet.
 */
class ZammadAdminController extends Controller {
    use ResolvesPluginOrgContext;

    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = ZammadConnection::query()->where('organization_id', $organization->id)->first();

        $sqids = app(SqidEncoder::class);
        $projects = Project::query()->orderBy('name')->limit(500)->get(['id', 'name'])
            ->map(fn (Project $p): array => ['sqid' => $sqids->encode(Project::class, (int) $p->id), 'name' => $p->name]);

        // Queue-Map für die View in Projekt-Sqids übersetzen.
        $queueRows = [];
        foreach (($connection->queue_map ?? []) as $groupId => $projectId) {
            $queueRows[] = [
                'group_id' => (int) $groupId,
                'project_sqid' => $sqids->encode(Project::class, (int) $projectId),
            ];
        }

        return view('zammad::admin.index', [
            'connection' => $connection,
            'projects' => $projects,
            'queueRows' => $queueRows,
            'defaultProjectSqid' => $connection?->default_project_id !== null
                ? $sqids->encode(Project::class, (int) $connection->default_project_id)
                : null,
            'health' => $connection instanceof ZammadConnection && $connection->isActive()
                ? $this->probe($connection)
                : null,
        ]);
    }

    /** Legt die Anbindung an oder aktualisiert sie (Token nur bei Eingabe). */
    public function store(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'string', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'default_project' => ['nullable', 'string'],
            'queue_group' => ['array'],
            'queue_group.*' => ['nullable', 'integer', 'min:1'],
            'queue_project' => ['array'],
            'queue_project.*' => ['nullable', 'string'],
            'resolved_state' => ['nullable', 'string', 'max:64'],
        ]);

        $baseUrl = trim((string) $data['base_url']);
        if (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            return back()->with('error', __('zammad.flash.invalid_url'))->withInput();
        }

        /** @var ZammadConnection $connection */
        $connection = ZammadConnection::query()->firstOrNew(['organization_id' => $organization->id]);

        $attributes = [
            'name' => (string) $data['name'],
            'base_url' => rtrim($baseUrl, '/'),
            'active' => (bool) ($data['active'] ?? false),
            'default_project_id' => $this->resolveProjectId($organization, $data['default_project'] ?? null),
            'queue_map' => $this->buildQueueMap($organization, $request),
            // Status-Rückkanal (opt-in): leeres Feld = aus.
            'resolved_state' => filled($data['resolved_state'] ?? null) ? trim((string) $data['resolved_state']) : null,
            'created_by' => $connection->exists ? $connection->created_by : $admin->id,
        ];

        // Token/Secret nur bei Eingabe setzen — nie leere Strings in encrypted-Felder.
        $token = trim((string) ($data['api_token'] ?? ''));
        if ($token !== '') {
            $attributes['api_token'] = $token;
        } elseif (! $connection->exists) {
            return back()->with('error', __('zammad.flash.token_required'))->withInput();
        }

        $secret = trim((string) ($data['webhook_secret'] ?? ''));
        $attributes['webhook_secret'] = $secret !== '' ? $secret : null;

        $connection->forceFill($attributes)->save();
        $connection->audit('zammad.connection_saved', ['by_user_id' => (int) $admin->id, 'active' => $connection->active]);

        return back()->with('success', __('zammad.flash.saved'));
    }

    /** Manueller Ticket-Import (Polling-Äquivalent, auditiert). */
    public function sync(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = ZammadConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof ZammadConnection || ! $connection->isActive()) {
            return back()->with('error', __('zammad.flash.no_connection'));
        }

        Artisan::call('zammad:sync', ['--organization' => (string) $organization->id]);
        $connection->audit('zammad.sync_manual', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('zammad.flash.sync_done'));
    }

    /** Deaktiviert die Anbindung; Aufgaben und Referenzen bleiben erhalten (DoD). */
    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = ZammadConnection::query()->where('organization_id', $organization->id)->first();
        if ($connection instanceof ZammadConnection) {
            $connection->forceFill(['active' => false])->save();
            $connection->audit('zammad.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return back()->with('success', __('zammad.flash.disconnected'));
    }

    /**
     * Baut die Queue→Projekt-Map aus paarigen Formularzeilen (nur eigene Projekte).
     *
     * @return array<int, int>  Zammad-Gruppen-ID => Projekt-ID
     */
    private function buildQueueMap(Organization $organization, Request $request): array {
        $groups = (array) $request->input('queue_group', []);
        $projects = (array) $request->input('queue_project', []);

        $map = [];
        foreach ($groups as $i => $groupId) {
            $gid = is_numeric($groupId) ? (int) $groupId : 0;
            $projectId = $this->resolveProjectId($organization, $projects[$i] ?? null);
            if ($gid > 0 && $projectId !== null) {
                $map[$gid] = $projectId;
            }
        }

        return $map;
    }

    /** Sqid → Projekt-ID der eigenen Organisation (Mandantengrenze), sonst null. */
    private function resolveProjectId(Organization $organization, mixed $sqid): ?int {
        if (! is_string($sqid) || $sqid === '') {
            return null;
        }
        $decoded = app(SqidEncoder::class)->decode(Project::class, $sqid);
        if ($decoded === null) {
            return null;
        }

        return Project::query()->whereKey($decoded)->where('organization_id', $organization->id)->exists()
            ? $decoded
            : null;
    }

    /** @return array{ok: bool} */
    private function probe(ZammadConnection $connection): array {
        try {
            return ['ok' => app(ZammadGatewayFactory::class)->for($connection)->ping()];
        } catch (Throwable) {
            return ['ok' => false];
        }
    }
}
