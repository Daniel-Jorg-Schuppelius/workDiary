<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphTasksController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Http\Controllers;

use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Plugins\Msgraph\Api\{MsgraphTasksOAuth, MsgraphTodoClient};
use App\Plugins\Msgraph\Enums\{MsgraphConnectionStatus, MsgraphTaskListLinkStatus};
use App\Plugins\Msgraph\Models\{MsgraphTaskConnection, MsgraphTaskListLink};
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Throwable;

/**
 * OAuth-Verbindungsflow + Listen-Zuordnungen des TO-DO-SYNCS (Feature 102,
 * Schnitt E) — sechster Grant (`Tasks.ReadWrite`), verwaltet im
 * Msgraph-Admin-Panel. Zuordnungen sind explizit (Preflight-Gedanke des
 * Todoist-Musters): nur bewusst verknüpfte Listen werden synchronisiert;
 * die Liste wird serverseitig gegen die Graph-Listenliste validiert.
 */
class MsgraphTasksController extends ConnectionOAuthController {
    use ResolvesPluginOrgContext;

    protected function oauth(): PluginOAuthGrant {
        return app(MsgraphTasksOAuth::class);
    }

    protected function isConfigured(): bool {
        return MsgraphConfig::isConfigured();
    }

    protected function connectionModel(): string {
        return MsgraphTaskConnection::class;
    }

    protected function stateCachePrefix(): string {
        return 'msgraph-tasks-oauth-state';
    }

    protected function overviewRouteName(): string {
        return 'admin.msgraph.index';
    }

    protected function pluginKey(): string {
        return 'msgraph_tasks';
    }

    protected function pluginId(): string {
        return MsgraphPlugin::ID;
    }

    protected function connectedStatus(): string {
        return MsgraphConnectionStatus::Active->value;
    }

    protected function disconnectedStatus(): string {
        return MsgraphConnectionStatus::Disconnected->value;
    }

    /** Bestätigte Kontoidentität laden (Fehler unkritisch). */
    protected function afterConnected(Model $connection, User $admin): void {
        if (! $connection instanceof MsgraphTaskConnection) {
            return;
        }
        try {
            $connection->forceFill(['account_label' => (new MsgraphTodoClient($connection))->account()['label']])->save();
        } catch (Throwable) {
            // Anzeige-Komfort; die Verbindung bleibt nutzbar.
        }
    }

    /** Listen-Zuordnung anlegen (Liste serverseitig validiert, Todoist-Muster). */
    public function storeLink(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = MsgraphTaskConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof MsgraphTaskConnection || ! $connection->isActive()) {
            return back()->with('error', __('msgraph::msgraph_tasks.flash.no_connection'));
        }

        $data = $request->validate([
            'todo_list_id' => ['required', 'string', 'max:512'],
            'target_kind' => ['required', 'in:' . MsgraphTaskListLink::KIND_PROJECT . ',' . MsgraphTaskListLink::KIND_GLOBAL_KANBAN],
            // Sqid aus dem Formular; die rohe ID bleibt für Altaufrufer lesbar.
            'project_id' => ['required_if:target_kind,' . MsgraphTaskListLink::KIND_PROJECT, 'nullable', 'string', 'max:64'],
            'sync_mode' => ['required', 'in:' . implode(',', [
                MsgraphTaskListLink::MODE_TODO_TO_WORKDIARY,
                MsgraphTaskListLink::MODE_WORKDIARY_TO_TODO,
                MsgraphTaskListLink::MODE_BIDIRECTIONAL,
            ])],
        ]);

        // Liste serverseitig auflösen — kein Unterschieben fremder IDs.
        try {
            $list = collect((new MsgraphTodoClient($connection))->lists())
                ->firstWhere('id', (string) $data['todo_list_id']);
        } catch (Throwable) {
            $list = null;
        }
        if (! is_array($list)) {
            return back()->with('error', __('msgraph::msgraph_tasks.flash.list_invalid'));
        }

        $projectId = null;
        if ($data['target_kind'] === MsgraphTaskListLink::KIND_PROJECT) {
            $decoded = Sqid::decodeOrNumeric(Project::class, (string) $data['project_id']);
            $project = $decoded !== null ? Project::query()->where('organization_id', $organization->id)->find($decoded) : null;
            if ($project === null) {
                return back()->with('error', __('msgraph::msgraph_tasks.flash.project_invalid'));
            }
            $projectId = (int) $project->id;
        }

        $link = MsgraphTaskListLink::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'todo_list_id' => $list['id']],
            [
                'todo_list_name' => $list['name'],
                'target_kind' => (string) $data['target_kind'],
                'project_id' => $projectId,
                'sync_mode' => (string) $data['sync_mode'],
                'status' => MsgraphTaskListLinkStatus::Active,
            ],
        );
        $link->audit('msgraph_tasks.link_saved', ['list' => $list['name'], 'mode' => $link->sync_mode]);

        return back()->with('success', __('msgraph::msgraph_tasks.flash.link_saved'));
    }

    /** Zuordnung entfernen — Referenzen/Aufgaben bleiben unangetastet. */
    public function destroyLink(MsgraphTaskListLink $link): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        abort_unless((int) $link->organization_id === (int) $organization->id, 404);

        $link->audit('msgraph_tasks.link_removed', ['list' => $link->todo_list_name ?? $link->todo_list_id]);
        $link->delete();

        return back()->with('success', __('msgraph::msgraph_tasks.flash.link_removed'));
    }
}
