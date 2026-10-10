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
use App\Models\Platform\{Organization, PluginState};
use App\Models\Project\Project;
use App\Models\ServiceTicket\ServiceQueue;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Zammad\Models\ZammadConnection;
use App\Plugins\Zammad\Services\ZammadTicketImporter;
use App\Plugins\Zammad\ZammadPlugin;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\{ErrorText, SqidEncoder, UrlSafety};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * Zammad-Admin-Panel (Feature 060, MVP-129): eine Anbindung je Organisation
 * (Basis-URL, Token verschlüsselt, Queue→Projekt-Zuordnung), manueller Import,
 * Wechsel des Ticketziels (Aufgaben ↔ Service-Tickets) und Trennen. Der Token
 * erscheint nie in Views oder Audit-Payloads ({@see ZammadConnection::$hidden});
 * ein leeres Token-Feld beim Speichern lässt das bestehende Token unangetastet.
 */
class ZammadAdminController extends Controller {
    use ResolvesPluginOrgContext;

    private const HELPDESK_MODULE = 'module.helpdesk';

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

        // Service-Tickets setzen das Helpdesk-Modul voraus (Queues unter helpdesk.queues).
        $serviceTickets = app(FeatureFlagResolver::class)->isEnabled(self::HELPDESK_MODULE);
        $queues = $serviceTickets
            ? ServiceQueue::query()->where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn (ServiceQueue $q): array => ['sqid' => $sqids->encode(ServiceQueue::class, (int) $q->id), 'name' => $q->name, 'id' => (int) $q->id])
            : collect();

        return view('zammad::admin.index', [
            'connection' => $connection,
            'serviceTicketsAvailable' => $serviceTickets,
            'queues' => $queues,
            'projects' => $projects,
            'queueRows' => $queueRows,
            'defaultProjectSqid' => $connection?->default_project_id !== null
                ? $sqids->encode(Project::class, (int) $connection->default_project_id)
                : null,
            // Gespeicherter Stand statt Ping beim Seitenaufruf (UI-Fuzz 2026-09-21).
            'healthState' => PluginState::forContext(ZammadPlugin::ID, $organization->id),
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
            'is_limited_to_mapped_groups' => ['nullable', 'boolean'],
            'resolved_state' => ['nullable', 'string', 'max:64'],
            'time_unit' => ['nullable', 'in:minute,hour'],
            'allow_private_network' => ['nullable', 'boolean'],
        ]);

        $baseUrl = trim((string) $data['base_url']);
        $allowPrivate = (bool) ($data['allow_private_network'] ?? false);
        if (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            return back()->with('error', __('zammad::zammad.flash.invalid_url'))->withInput();
        }
        // Konfigurationszeit-Prüfung ohne DNS; verbindlich prüft das Gateway vor jedem Abruf.
        if (! $allowPrivate && ! UrlSafety::isAcceptableExternalHttpUrl($baseUrl)) {
            return back()->with('error', __('zammad::zammad.flash.private_url_blocked'))->withInput();
        }

        /** @var ZammadConnection $connection */
        $connection = ZammadConnection::query()->firstOrNew(['organization_id' => $organization->id]);

        $attributes = [
            'name' => (string) $data['name'],
            'base_url' => rtrim($baseUrl, '/'),
            'active' => (bool) ($data['active'] ?? false),
            'default_project_id' => $this->resolveProjectId($organization, $data['default_project'] ?? null),
            'queue_map' => $this->buildQueueMap($organization, $request),
            'is_limited_to_mapped_groups' => (bool) ($data['is_limited_to_mapped_groups'] ?? false),
            // Status-Rückkanal (opt-in): leeres Feld = aus.
            'resolved_state' => filled($data['resolved_state'] ?? null) ? trim((string) $data['resolved_state']) : null,
            // Zeit-Rückkanal (opt-in): Einheit wie Zammads „Time Accounting Unit“, leer = aus.
            'time_unit' => filled($data['time_unit'] ?? null) ? (string) $data['time_unit'] : null,
            'allow_private_network' => $allowPrivate,
            'created_by' => $connection->exists ? $connection->created_by : $admin->id,
        ];

        // Token/Secret nur bei Eingabe setzen — nie leere Strings in encrypted-Felder.
        $token = trim((string) ($data['api_token'] ?? ''));
        if ($token !== '') {
            $attributes['api_token'] = $token;
        } elseif (! $connection->exists) {
            return back()->with('error', __('zammad::zammad.flash.token_required'))->withInput();
        }

        $secret = trim((string) ($data['webhook_secret'] ?? ''));
        if ($secret !== '') {
            $attributes['webhook_secret'] = $secret;
        }

        $connection->forceFill($attributes)->save();
        $connection->audit('zammad.connection_saved', [
            'by_user_id' => (int) $admin->id,
            'active' => $connection->active,
            'is_limited_to_mapped_groups' => $connection->is_limited_to_mapped_groups,
            'time_unit' => $connection->time_unit,
            'allow_private_network' => $connection->allow_private_network,
        ]);

        return back()->with('success', __('zammad::zammad.flash.saved'));
    }

    /**
     * Ticketziel wechseln (Feature 065, P8): Aufgaben oder Service-Tickets einer
     * Queue. Preflight und Migrationsprotokoll liegen im Importer; bereits
     * importierte Tickets bleiben, wo sie sind.
     */
    public function switchTarget(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'ticket_target' => ['required', 'in:task,service_ticket'],
            'service_queue' => ['nullable', 'string'],
        ]);

        $connection = ZammadConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof ZammadConnection) {
            return back()->with('error', __('zammad::zammad.flash.no_connection'));
        }

        $queue = null;
        if ($data['ticket_target'] === 'service_ticket') {
            if (! app(FeatureFlagResolver::class)->isEnabled(self::HELPDESK_MODULE)) {
                return back()->with('error', __('zammad::zammad.flash.helpdesk_required'));
            }
            $queueId = app(SqidEncoder::class)->decode(ServiceQueue::class, (string) ($data['service_queue'] ?? ''));
            $queue = $queueId !== null
                ? ServiceQueue::query()->whereKey($queueId)->where('organization_id', $organization->id)->first()
                : null;
            if (! $queue instanceof ServiceQueue) {
                return back()->withErrors(['service_queue' => __('zammad::zammad.flash.queue_required')])->withInput();
            }
        }

        try {
            app(ZammadTicketImporter::class)->switchTicketTarget($connection, (string) $data['ticket_target'], $queue, $admin);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('zammad::zammad.flash.target_switched'));
    }

    /** Manueller Ticket-Import (Polling-Äquivalent, auditiert). */
    public function sync(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = ZammadConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof ZammadConnection || ! $connection->isActive()) {
            return back()->with('error', __('zammad::zammad.flash.no_connection'));
        }

        // Queue statt Request (Vollscan 2026-08-23, J17): ein Voll-Sync im Web-
        // Request lief in den PHP-Timeout; der Worker hat Retry und Laufzeitbudget.
        Artisan::queue('zammad:sync', ['--organization' => (string) $organization->id]);
        $connection->audit('zammad.sync_manual', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('zammad::zammad.flash.sync_done'));
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

        return back()->with('success', __('zammad::zammad.flash.disconnected'));
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
}
