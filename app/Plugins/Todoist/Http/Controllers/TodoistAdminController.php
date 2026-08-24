<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Todoist\Http\Controllers;

use App\Models\{ExternalReference, Project, TodoistConnection, TodoistProjectLink, User};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
use App\Plugins\Todoist\Api\{TodoistApiClient, TodoistOAuth};
use App\Plugins\Todoist\Services\TodoistPreflightService;
use App\Plugins\Todoist\{TodoistConfig, TodoistPlugin};
use App\Services\SqidEncoder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Throwable;

/**
 * Todoist-Admin-Panel + OAuth-Verbindungsflow (Feature 055, MVP-111/116).
 * Der OAuth-Flow (state einmalig, org-/sitzungsgebunden; ohne PKCE — Todoist
 * unterstützt es nicht) läuft über die gemeinsame Basis
 * {@see ConnectionOAuthController}; Tokens erscheinen nie in Logs,
 * Fehlermeldungen oder Audit-Payloads. Scope ist bewusst nur
 * `data:read_write` — keine Lösch-Scopes (keine Löschweitergabe, DoD 055).
 */
class TodoistAdminController extends ConnectionOAuthController {
    use ResolvesPluginOrgContext;

    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = TodoistConnection::query()->where('organization_id', $organization->id)->first();

        // Verfügbare Todoist-Projekte für die Zuordnung (nur bei aktiver Verbindung).
        $remoteProjects = [];
        if ($connection instanceof TodoistConnection && $connection->isActive()) {
            try {
                $remoteProjects = (new TodoistApiClient($connection))->getProjects();
            } catch (Throwable) {
                // Panel bleibt nutzbar; der Health-Status meldet API-Probleme.
            }
        }

        return view('todoist::admin.index', [
            'configured' => TodoistConfig::isConfigured(),
            'connection' => $connection,
            'links' => TodoistProjectLink::query()->with('project:id,name')->orderBy('todoist_project_name')->get(),
            'remoteProjects' => $remoteProjects,
            'projects' => Project::query()->orderBy('name')->limit(500)->get(['id', 'name']),
        ]);
    }

    // ── OAuth-Flow: Hooks der gemeinsamen Basis (Vollreview W3a) ──

    protected function oauth(): PluginOAuthGrant {
        return app(TodoistOAuth::class);
    }

    protected function isConfigured(): bool {
        return TodoistConfig::isConfigured();
    }

    protected function connectionModel(): string {
        return TodoistConnection::class;
    }

    protected function stateCachePrefix(): string {
        return 'todoist-oauth-state';
    }

    protected function overviewRouteName(): string {
        return 'admin.todoist.index';
    }

    protected function pluginKey(): string {
        return 'todoist';
    }

    protected function connectedStatus(): string {
        return TodoistConnection::STATUS_ACTIVE;
    }

    protected function disconnectedStatus(): string {
        return TodoistConnection::STATUS_DISCONNECTED;
    }

    /** Ohne PKCE (Todoist unterstützt es nicht); state bleibt Einmal-Token. */
    protected function usesPkce(): bool {
        return false;
    }

    /**
     * todoist_connections hat keine Health-Spalten (last_error_at & Co.).
     *
     * @return array<string, mixed>
     */
    protected function connectionErrorResets(): array {
        return ['last_error' => null];
    }

    /** Verbundenen Todoist-Benutzer festhalten (für Webhook-Org-Zuordnung, P5). */
    protected function afterConnected(Model $connection, User $admin): void {
        /** @var TodoistConnection $connection */
        try {
            $user = (new TodoistApiClient($connection))->getUser();
            $connection->forceFill([
                'todoist_user_id' => isset($user['id']) ? (string) $user['id'] : null,
                'todoist_user_email' => isset($user['email']) ? (string) $user['email'] : null,
            ])->save();
        } catch (Throwable) {
            // Verbindungsdaten bleiben gültig; der Health-Check meldet API-Probleme.
        }
    }

    /** Legt eine Projektzuordnung an (Entwurf; Aktivierung erst nach Preflight). */
    public function storeLink(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'todoist_project_id' => ['required', 'string', 'max:64'],
            'todoist_project_name' => ['nullable', 'string', 'max:191'],
            'target_kind' => ['required', 'in:project,global_kanban'],
            'project' => ['nullable', 'string'],
            'sync_mode' => ['required', 'in:todoist_to_workdiary,workdiary_to_todoist,bidirectional'],
        ]);

        $projectId = null;
        if ($data['target_kind'] === TodoistProjectLink::KIND_PROJECT) {
            $decoded = ! empty($data['project']) ? app(SqidEncoder::class)->decode(Project::class, (string) $data['project']) : null;
            $project = $decoded !== null ? Project::query()->find($decoded) : null;
            if (! $project instanceof Project) {
                return back()->with('error', __('todoist.flash.link_project_required'));
            }
            $projectId = (int) $project->id;
        }

        $link = TodoistProjectLink::query()->firstOrNew([
            'organization_id' => $organization->id,
            'todoist_project_id' => (string) $data['todoist_project_id'],
        ]);
        $link->fill([
            'todoist_project_name' => $data['todoist_project_name'] ?? $link->todoist_project_name,
            'target_kind' => (string) $data['target_kind'],
            'project_id' => $projectId,
            'sync_mode' => (string) $data['sync_mode'],
            'status' => $link->exists ? $link->status : TodoistProjectLink::STATUS_DRAFT,
        ])->save();
        $link->audit('todoist.link_saved', ['todoist_project_id' => $link->todoist_project_id, 'sync_mode' => $link->sync_mode]);

        return back()->with('success', __('todoist.flash.link_saved'));
    }

    /** Preflight (MVP-112): Kennzahlen + Kollaborator-Zuordnung VOR der Aktivierung. */
    public function preflight(TodoistProjectLink $link, TodoistPreflightService $preflights): View|RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        abort_unless((int) $link->organization_id === (int) $organization->id, 404);

        $connection = TodoistConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof TodoistConnection || ! $connection->isActive()) {
            return back()->with('error', __('todoist.flash.no_connection'));
        }

        try {
            $result = $preflights->forProject($organization, $connection, $link->todoist_project_id);
        } catch (Throwable $e) {
            return back()->with('error', __('todoist.flash.preflight_failed', ['class' => class_basename($e)]));
        }

        // Abschnitte für die Status-Zuordnung gleich mitladen.
        $sections = [];
        try {
            $sections = (new TodoistApiClient($connection))->getSections($link->todoist_project_id);
        } catch (Throwable) {
            // optional
        }

        return view('todoist::admin.preflight', [
            'link' => $link->load('sectionLinks'),
            'result' => $result,
            'sections' => $sections,
            'users' => User::query()->where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Aktiviert/pausiert eine Zuordnung (bewusster Admin-Schritt nach Preflight). */
    public function setLinkStatus(Request $request, TodoistProjectLink $link): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        abort_unless((int) $link->organization_id === (int) $organization->id, 404);

        $status = (string) $request->validate(['status' => ['required', 'in:active,paused']])['status'];
        $link->forceFill(['status' => $status])->save();
        $link->audit('todoist.link_status', ['status' => $status]);

        return back()->with('success', __('todoist.flash.link_saved'));
    }

    public function destroyLink(TodoistProjectLink $link): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        abort_unless((int) $link->organization_id === (int) $organization->id, 404);

        $link->sectionLinks()->delete();
        $link->delete(); // Auditable loggt 'deleted' automatisch

        return redirect()->route('admin.todoist.index')->with('success', __('todoist.flash.link_removed'));
    }

    /** Speichert Abschnitts→Status-Zuordnungen (nicht zugeordnet = Status unangetastet). */
    public function storeSectionLinks(Request $request, TodoistProjectLink $link): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        abort_unless((int) $link->organization_id === (int) $organization->id, 404);

        $data = $request->validate([
            'sections' => ['nullable', 'array'],
            'sections.*.status' => ['nullable', 'in:open,in_progress'],
            'sections.*.name' => ['nullable', 'string', 'max:191'],
        ]);

        foreach ((array) ($data['sections'] ?? []) as $sectionId => $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === '') {
                $link->sectionLinks()->where('todoist_section_id', (string) $sectionId)->delete();

                continue;
            }
            $link->sectionLinks()->updateOrCreate(
                ['todoist_section_id' => (string) $sectionId],
                ['organization_id' => $organization->id, 'task_status' => $status, 'name' => $row['name'] ?? null],
            );
        }
        $link->audit('todoist.sections_saved', ['count' => $link->sectionLinks()->count()]);

        return back()->with('success', __('todoist.flash.sections_saved'));
    }

    /** Ordnet einen Todoist-Kollaborator einem Org-Benutzer zu (oder löst die Zuordnung). */
    public function assignCollaborator(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'collaborator_id' => ['required', 'string', 'max:64'],
            'user' => ['nullable', 'string'],
        ]);

        $reference = ExternalReference::query()
            ->forPlugin($organization, TodoistPlugin::ID, TodoistPlugin::EXT_TYPE_COLLABORATOR)
            ->forExternalId((string) $data['collaborator_id'])
            ->first();

        $connection = TodoistConnection::query()->where('organization_id', $organization->id)->first();

        if (empty($data['user'])) {
            $reference?->delete();
            $connection?->audit('todoist.collaborator_unassigned', ['collaborator_id' => (string) $data['collaborator_id']]);

            return back()->with('success', __('todoist.flash.collaborator_unassigned'));
        }

        $userId = app(SqidEncoder::class)->decode(User::class, (string) $data['user']);
        $user = $userId !== null
            ? User::query()->where('organization_id', $organization->id)->find($userId)
            : null;
        if (! $user instanceof User) {
            return back()->with('error', __('todoist.flash.collaborator_invalid'));
        }

        ExternalReference::link($organization, TodoistPlugin::ID, TodoistPlugin::EXT_TYPE_COLLABORATOR, $user, (string) $data['collaborator_id']);
        $connection?->audit('todoist.collaborator_assigned', [
            'collaborator_id' => (string) $data['collaborator_id'],
            'user_id' => (int) $user->getKey(),
        ]);

        return back()->with('success', __('todoist.flash.collaborator_assigned'));
    }

    /** Manueller Vollabgleich (MVP-116): auditierter Admin-Vorgang. */
    public function sync(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = TodoistConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof TodoistConnection || ! $connection->isActive()) {
            return back()->with('error', __('todoist.flash.no_connection'));
        }

        // Queue statt Request (Vollscan 2026-08-23, J17): ein Voll-Sync im Web-
        // Request lief in den PHP-Timeout; der Worker hat Retry und Laufzeitbudget.
        Artisan::queue('todoist:sync', ['--organization' => (string) $organization->id, '--full' => true]);
        $connection->audit('todoist.sync_manual', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('todoist.flash.sync_done'));
    }
}
