<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagesTaskImportLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use App\Enums\Integration\TaskImportLinkStatus;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\Organization;
use App\Models\Project\{Project, Task};
use App\Plugins\Support\OAuthConnectionStatus;
use App\Support\SqidEncoder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;
use Throwable;

/**
 * Seite, Zuordnungen, Vorschau, Verknüpfen und Abgleich der Aufgabenimporte —
 * gemeinsam für die Anmeldewege OAuth ({@see TaskImportAdminController}) und
 * Token ({@see TokenTaskImportAdminController}). Jede Aktion verlangt den
 * Org-Admin und prüft die Zugehörigkeit der Zuordnung.
 *
 * @template TLink of Model&TaskImportLink
 * @template TConnection of Model&TaskImportConnection
 */
trait ManagesTaskImportLinks {
    /** @return AbstractTaskImportService<TLink, TConnection> */
    abstract protected function importService(): AbstractTaskImportService;

    /** @return class-string<TLink> */
    abstract protected function linkModel(): string;

    /**
     * Projekte der Quelle für den Zuordnungsdialog.
     *
     * @return list<array{id: string, key: string, name: string}>
     */
    abstract protected function remoteProjects(int $organizationId): array;

    abstract protected function providerName(): string;

    /** Bezeichnung der Quelle („Site“, „Workspace“, „Instanz“). */
    abstract protected function sourceNoun(): string;

    abstract protected function pluginId(): string;

    abstract protected function isConfigured(): bool;

    abstract protected function backToOverview(): RedirectResponse;

    /** Anmeldeweg für die Seite: `oauth` oder `token`. */
    abstract protected function connectMode(): string;

    /** Zusätzlicher Abschnitt eines Plugins unter den Zuordnungen (View-Name), sonst null. */
    protected function extraView(): ?string {
        return null;
    }

    /** @return array<string, mixed> Daten des zusätzlichen Abschnitts */
    protected function extraViewData(Organization $organization): array {
        return [];
    }

    // ── Seite ───────────────────────────────────────────────────────────

    public function index(): View {
        $organization = $this->organization($this->admin());
        $connection = $this->importService()->connection((int) $organization->id);
        $model = $this->linkModel();

        return view('plugins.task-import.admin', $this->texts() + [
            'configured' => $this->isConfigured(),
            'connection' => $connection,
            'connected' => $connection !== null && $connection->getAttribute('status') === OAuthConnectionStatus::Active,
            'links' => $model::query()->where('organization_id', $organization->id)->with('project:id,name,customer_id')
                ->orderBy('source_project_name')->orderBy('id')->paginate(25)->withQueryString(),
            'openInbox' => IntegrationInboxItem::openCount((int) $organization->id, $this->pluginId()),
            'connectMode' => $this->connectMode(),
            'extraView' => $this->extraView(),
        ] + $this->extraViewData($organization));
    }

    // ── Zuordnungen ─────────────────────────────────────────────────────

    public function createLink(): View|RedirectResponse {
        $organization = $this->organization($this->admin());
        if ($this->importService()->connection((int) $organization->id)?->isReady() !== true) {
            return $this->backToOverview()->with('error', $this->flashMessage('not_connected'));
        }
        try {
            $remote = $this->remoteProjects((int) $organization->id);
        } catch (Throwable $e) {
            report($e);

            return $this->backToOverview()->with('error', $this->flashMessage('remote_failed', ['class' => class_basename($e)]));
        }
        $model = $this->linkModel();
        $mapped = $model::query()->where('organization_id', $organization->id)->pluck('source_project_id')->all();

        return view('plugins.task-import._link_dialog', $this->texts() + [
            'remoteProjects' => array_values(array_filter($remote, static fn (array $p): bool => ! in_array($p['id'], $mapped, true))),
            'projects' => Project::query()->where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name', 'customer_id', 'foreign_customer_id']),
        ]);
    }

    public function storeLink(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $data = $request->validate([
            'source_project_id' => ['required', 'string', 'max:64'],
            'project' => ['required', 'string', 'max:64'],
        ]);
        $project = $this->ownedModel(Project::class, (string) $data['project'], $organization);
        if (! $project instanceof Project) {
            return back()->withErrors(['project' => $this->flashMessage('project_invalid')]);
        }
        try {
            $remote = collect($this->remoteProjects((int) $organization->id))->firstWhere('id', (string) $data['source_project_id']);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $this->flashMessage('remote_failed', ['class' => class_basename($e)]));
        }
        if ($remote === null) {
            return back()->withErrors(['source_project_id' => $this->flashMessage('source_project_unknown')]);
        }

        $model = $this->linkModel();
        if ($model::query()->where('organization_id', $organization->id)->where('source_project_id', $remote['id'])->exists()) {
            return back()->withErrors(['source_project_id' => $this->flashMessage('link_exists')]);
        }
        $link = $model::query()->create([
            'organization_id' => $organization->id,
            'source_project_id' => $remote['id'],
            'source_project_key' => $remote['key'] !== '' ? $remote['key'] : null,
            'source_project_name' => $remote['name'],
            'project_id' => $project->id,
            'status' => TaskImportLinkStatus::Pending,
            'created_by' => $admin->id,
        ]);
        // @phpstan-ignore method.notFound (Auditable)
        $link->audit($this->pluginId() . '.link_created', ['source_project_id' => $remote['id'], 'project_id' => (int) $project->id]);

        return redirect()->route($this->routePrefix() . '.links.preview', $link)->with('success', $this->flashMessage('link_created'));
    }

    /** Vorschau ohne Schreibwirkung; von hier aus bestätigen oder einzeln verknüpfen. */
    public function preview(string $link): View|RedirectResponse {
        $organization = $this->organization($this->admin());
        $model = $this->findLink($link, $organization);
        try {
            $preview = $this->importService()->preview($organization, $model);
        } catch (Throwable $e) {
            report($e);

            return $this->backToOverview()->with('error', $this->flashMessage('preview_failed', ['class' => class_basename($e)]));
        }

        return view('plugins.task-import.preview', $this->texts() + [
            'link' => $model,
            'preview' => $preview,
            'candidates' => $this->importService()->unlinkedTasks($model),
        ]);
    }

    public function confirm(string $link): RedirectResponse {
        $admin = $this->admin();
        $model = $this->findLink($link, $this->organization($admin));
        if (! $model->getAttribute('status')->canTransitionTo(TaskImportLinkStatus::Active)) {
            return $this->backToOverview()->with('error', $this->flashMessage('status_invalid'));
        }
        $model->forceFill([
            'status' => TaskImportLinkStatus::Active,
            'import_confirmed_at' => CarbonImmutable::now(),
            'confirmed_by' => $admin->id,
        ])->save();
        // @phpstan-ignore method.notFound (Auditable)
        $model->audit($this->pluginId() . '.link_confirmed', ['by_user_id' => (int) $admin->id]);

        return $this->backToOverview()->with('success', $this->flashMessage('link_confirmed'));
    }

    /** Bestehende Aufgabe des Zielprojekts ausdrücklich mit einer Quellaufgabe verknüpfen. */
    public function attach(Request $request, string $link): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $model = $this->findLink($link, $organization);
        // Fremd-IDs sind Ziffern, Schlüssel oder UUIDs (Plane); `remote_id` heißt app-weit sonst Fernwartungs-ID.
        $data = $request->validate([
            'remote_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/'],
            'task' => ['required', 'string', 'max:64'],
        ], [], ['remote_id' => __('integration.task_import.preview.col.remote', ['provider' => $this->providerName()])]);
        $task = $this->ownedModel(Task::class, (string) $data['task'], $organization);
        if (! $task instanceof Task) {
            return back()->with('error', __('integration.task_import.attach_refused.wrong_target'));
        }

        try {
            $outcome = $this->importService()->attach($organization, $model, (string) $data['remote_id'], $task);
        } catch (TaskImportAttachException $e) {
            return back()->with('error', __('integration.task_import.attach_refused.' . $e->reason));
        } catch (TaskImportBusyException) {
            return back()->with('error', $this->flashMessage('busy'));
        }
        // @phpstan-ignore method.notFound (Auditable)
        $model->audit($this->pluginId() . '.task_attached', ['remote_id' => (string) $data['remote_id'], 'task_id' => (int) $task->id]);

        return back()->with('success', $this->flashMessage($outcome === 'conflicts' ? 'attached_with_conflicts' : 'attached'));
    }

    public function pause(string $link): RedirectResponse {
        return $this->changeStatus($link, TaskImportLinkStatus::Paused);
    }

    public function resume(string $link): RedirectResponse {
        return $this->changeStatus($link, TaskImportLinkStatus::Active);
    }

    /** Zuordnung entfernen; Aufgaben, Referenzen und Zeiten bleiben. */
    public function destroyLink(string $link): RedirectResponse {
        $admin = $this->admin();
        $model = $this->findLink($link, $this->organization($admin));
        // @phpstan-ignore method.notFound (Auditable)
        $model->audit($this->pluginId() . '.link_removed', ['source_project_id' => $model->sourceProjectId()]);
        $model->delete();

        return $this->backToOverview()->with('success', $this->flashMessage('link_removed'));
    }

    /** Manueller Abgleich — dieselbe Strecke wie der Scheduler. */
    public function syncNow(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        try {
            $counters = $this->importService()->run($organization);
        } catch (TaskImportBusyException) {
            return $this->backToOverview()->with('error', $this->flashMessage('busy'));
        } catch (Throwable $e) {
            report($e);

            return $this->backToOverview()->with('error', $this->flashMessage('sync_failed', ['class' => class_basename($e)]));
        }
        $connection = $this->importService()->connection((int) $organization->id);
        // @phpstan-ignore method.notFound (Auditable)
        $connection?->audit($this->pluginId() . '.synced', ['by_user_id' => (int) $admin->id] + $counters);

        return $this->backToOverview()->with('success', $this->flashMessage('synced', array_map('strval', $counters)));
    }

    // ── Intern ──────────────────────────────────────────────────────────

    protected function routePrefix(): string {
        return 'admin.' . $this->pluginId();
    }

    /** @param array<string, string> $replace */
    protected function flashMessage(string $name, array $replace = []): string {
        $message = __('integration.task_import.flash.' . $name, $replace + ['provider' => $this->providerName(), 'source' => $this->sourceNoun()]);

        return is_string($message) ? $message : $name;
    }

    /** @return array{provider: string, sourceNoun: string, routePrefix: string, pluginId: string} */
    private function texts(): array {
        return [
            'provider' => $this->providerName(),
            'sourceNoun' => $this->sourceNoun(),
            'routePrefix' => $this->routePrefix(),
            'pluginId' => $this->pluginId(),
        ];
    }

    private function changeStatus(string $link, TaskImportLinkStatus $target): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $model = $this->findLink($link, $organization);
        $resumable = $target !== TaskImportLinkStatus::Active
            || ($model->getAttribute('import_confirmed_at') !== null && $this->importService()->connection((int) $organization->id)?->isReady() === true);
        if (! $model->getAttribute('status')->canTransitionTo($target) || ! $resumable) {
            return $this->backToOverview()->with('error', $this->flashMessage('status_invalid'));
        }
        $model->forceFill([
            'status' => $target,
            'paused_reason' => $target === TaskImportLinkStatus::Paused ? TaskImportLink::PAUSED_MANUAL : null,
        ])->save();
        // @phpstan-ignore method.notFound (Auditable)
        $model->audit($this->pluginId() . '.link_' . $target->value, ['by_user_id' => (int) $admin->id]);

        return $this->backToOverview()->with('success', $this->flashMessage('link_' . $target->value));
    }

    /** @return TLink */
    protected function findLink(string $value, Organization $organization): Model {
        $model = $this->ownedModel($this->linkModel(), $value, $organization);
        abort_unless($model instanceof TaskImportLink, 404);

        return $model;
    }

    /**
     * Datensatz der eigenen Organisation aus Sqid (numerische ID als Rückfall).
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @return TModel|null
     */
    protected function ownedModel(string $class, string $value, Organization $organization): ?Model {
        $id = app(SqidEncoder::class)->decode($class, $value) ?? (ctype_digit($value) ? (int) $value : null);

        return $id === null ? null : $class::query()->withoutGlobalScopes()->whereKey($id)->where('organization_id', $organization->id)->first();
    }

    /** Beim Trennen: laufende Zuordnungen pausieren (Wiederverbinden mit derselben Quelle setzt fort). */
    private function pauseLinksOnDisconnect(int $organizationId): void {
        $model = $this->linkModel();
        $model::query()->where('organization_id', $organizationId)->where('status', TaskImportLinkStatus::Active)
            ->update(['status' => TaskImportLinkStatus::Paused->value, 'paused_reason' => TaskImportLink::PAUSED_DISCONNECTED]);
    }

    private function resumeLinksAfterReconnect(int $organizationId): void {
        $model = $this->linkModel();
        $model::query()->where('organization_id', $organizationId)
            ->where('status', TaskImportLinkStatus::Paused)
            ->where('paused_reason', TaskImportLink::PAUSED_DISCONNECTED)
            ->update(['status' => TaskImportLinkStatus::Active->value, 'paused_reason' => null]);
    }
}
