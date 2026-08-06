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

use App\Models\{MsgraphTaskConnection, MsgraphTaskListLink, Project, User};
use App\Plugins\Msgraph\Api\{MsgraphTasksOAuth, MsgraphTodoClient};
use App\Plugins\Msgraph\MsgraphConfig;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Support\{ConnectionOAuthController, PluginOAuthGrant};
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

    protected function connectedStatus(): string {
        return MsgraphTaskConnection::STATUS_ACTIVE;
    }

    protected function disconnectedStatus(): string {
        return MsgraphTaskConnection::STATUS_DISCONNECTED;
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
            return back()->with('error', __('msgraph_tasks.flash.no_connection'));
        }

        $data = $request->validate([
            'todo_list_id' => ['required', 'string', 'max:512'],
            'target_kind' => ['required', 'in:' . MsgraphTaskListLink::KIND_PROJECT . ',' . MsgraphTaskListLink::KIND_GLOBAL_KANBAN],
            'project_id' => ['required_if:target_kind,' . MsgraphTaskListLink::KIND_PROJECT, 'nullable', 'integer'],
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
            return back()->with('error', __('msgraph_tasks.flash.list_invalid'));
        }

        $projectId = null;
        if ($data['target_kind'] === MsgraphTaskListLink::KIND_PROJECT) {
            $project = Project::query()->where('organization_id', $organization->id)->find((int) $data['project_id']);
            if ($project === null) {
                return back()->with('error', __('msgraph_tasks.flash.project_invalid'));
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
                'status' => MsgraphTaskListLink::STATUS_ACTIVE,
            ],
        );
        $link->audit('msgraph_tasks.link_saved', ['list' => $list['name'], 'mode' => $link->sync_mode]);

        return back()->with('success', __('msgraph_tasks.flash.link_saved'));
    }

    /** Zuordnung entfernen — Referenzen/Aufgaben bleiben unangetastet. */
    public function destroyLink(MsgraphTaskListLink $link): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        abort_unless((int) $link->organization_id === (int) $organization->id, 404);

        $link->audit('msgraph_tasks.link_removed', ['list' => $link->todo_list_name ?? $link->todo_list_id]);
        $link->delete();

        return back()->with('success', __('msgraph_tasks.flash.link_removed'));
    }
}
