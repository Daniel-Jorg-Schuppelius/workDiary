<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Http\Controllers;

use App\Enums\Project\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\{Customer, ExternalReference, Organization, Project, User};
use App\Plugins\Toggl\{TogglConfig, TogglExportImporter, TogglImportService, TogglPlugin};
use App\Plugins\Toggl\Sources\TogglWorkspaceReader;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\View\View;

/**
 * Admin-Seite für den Toggl-Import: API-Sync auslösen, Detailed-Report-CSV
 * hochladen und die Inbox unzugeordneter Einträge bearbeiten (einem Kunden +
 * Projekt zuweisen oder verwerfen). Zusätzlich der einmalige Workspace-Export-
 * Import ({@see importExport()} / {@see runImportExport()}).
 */
class TogglController extends Controller {
    public function __construct(
        private readonly TogglImportService $service,
        private readonly TogglExportImporter $exportImporter = new TogglExportImporter,
    ) {
    }

    public function index(): View {
        $admin = $this->admin();
        $organization = $admin->organization;

        $groups = $organization !== null ? $this->service->openPendingGroups($organization) : collect();

        if ($organization instanceof Organization) {
            // Jede Inbox-Gruppe um einen Fuzzy-Vorschlag (Kunde + Projekt)
            // ergänzen, der im Formular vorausgewählt wird — nie automatisch
            // gebucht, nur als Vorbelegung.
            $groups = $groups->map(function (object $group) use ($organization): object {
                $customer = $this->service->suggestCustomer($organization, $group->client_name);
                $project = $this->service->suggestProject($organization, $customer, $group->project_name);

                return (object) [
                    'client_name' => $group->client_name,
                    'project_name' => $group->project_name,
                    'count' => $group->count,
                    'minutes' => $group->minutes,
                    'first_seen' => $group->first_seen,
                    'last_seen' => $group->last_seen,
                    'suggested_customer_sqid' => $customer?->sqid,
                    'suggested_project_sqid' => $project?->sqid,
                ];
            });
        }

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'company']);
        $projects = Project::query()
            ->whereNotNull('customer_id')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id']);

        return view('toggl::admin.import', [
            'groups' => $groups,
            'customers' => $this->customerOptions($customers),
            'projects' => $this->projectOptions($projects),
        ]);
    }

    public function sync(): RedirectResponse {
        $admin = $this->admin();

        $config = TogglConfig::resolve($admin->organization_id);
        if ($config['api_token'] === null) {
            return back()->withErrors(['api_token' => __('Kein Toggl API-Token hinterlegt.')]);
        }

        $to = CarbonImmutable::now();
        $from = $to->subDays(max(1, (int) $config['sync_window_days']));

        $result = $this->service->importFromApi($this->organization($admin), $config, $from, $to);

        return back()->with('status', $this->importMessage($result));
    }

    public function uploadCsv(Request $request): RedirectResponse {
        $admin = $this->admin();

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $content = ToolkitFile::read((string) $request->file('csv')->getRealPath());
        $config = TogglConfig::resolve($admin->organization_id);

        $result = $this->service->importFromCsv($this->organization($admin), $content, $config);

        return back()->with('status', $this->importMessage($result));
    }

    public function assign(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'client_name' => ['nullable', 'string', 'max:191'],
            'project_name' => ['nullable', 'string', 'max:191'],
            'customer_mode' => ['required', 'in:existing,new'],
            'customer_id' => ['nullable', 'string', 'required_if:customer_mode,existing'],
            'new_customer_name' => ['nullable', 'string', 'max:191', 'required_if:customer_mode,new'],
            'project_mode' => ['required', 'in:existing,new'],
            'project_id' => ['nullable', 'string', 'required_if:project_mode,existing'],
            'new_project_name' => ['nullable', 'string', 'max:191', 'required_if:project_mode,new'],
        ]);

        // Kunde + Projekt atomar auflösen bzw. anlegen, dann die Pendings buchen.
        // Nie automatisch anlegen: Neuanlage passiert nur auf explizite Wahl,
        // bestehende werden über die Auswahl referenziert (keine Duplikate).
        $result = DB::transaction(function () use ($organization, $data): array {
            $customer = $this->resolveCustomer($organization, $data);
            $project = $this->resolveProject($organization, $customer, $data);

            return $this->service->assignPending(
                $organization,
                $data['client_name'] ?? null,
                $data['project_name'] ?? null,
                $customer,
                $project,
            );
        });

        return back()->with('status', (string) __(':created gebucht, :skipped bereits vorhanden.', $result));
    }

    /** Mapping-Verwaltung: gemerkte Client-/Projekt-Zuordnungen einsehen/ändern. */
    public function mappings(): View {
        $admin = $this->admin();
        $organization = $admin->organization;

        $mappings = $organization instanceof Organization ? $this->service->mappings($organization) : collect();

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'company']);
        $projects = Project::query()
            ->whereNotNull('customer_id')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id']);

        return view('toggl::admin.mappings', [
            'mappings' => $mappings,
            'customers' => $this->customerOptions($customers),
            'projects' => $this->projectOptions($projects),
        ]);
    }

    /** Zeigt eine gemerkte Zuordnung auf einen anderen Kunden/ein anderes Projekt um. */
    public function updateMapping(Request $request, int $reference): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $ref = $this->findMapping($organization, $reference);

        if ($ref->external_type === TogglImportService::EXT_TYPE_CLIENT) {
            $target = Customer::query()->whereKey($this->decodeId(Customer::class, $request->input('target_id')))->firstOrFail();
        } else {
            $target = Project::query()->whereKey($this->decodeId(Project::class, $request->input('target_id')))->firstOrFail();
        }
        abort_unless((int) $target->organization_id === (int) $organization->id, 403);

        $ref->update([
            'referenceable_type' => $target->getMorphClass(),
            'referenceable_id' => $target->getKey(),
            'synced_at' => now(),
        ]);

        return back()->with('status', (string) __('Zuordnung aktualisiert.'));
    }

    /** Löscht eine gemerkte Zuordnung (künftige Importe matchen dann nicht mehr automatisch). */
    public function deleteMapping(int $reference): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $this->findMapping($organization, $reference)->delete();

        return back()->with('status', (string) __('Zuordnung entfernt.'));
    }

    /**
     * Einmaliger Import eines vollständigen Toggl-Workspace-Exports. Zeigt das
     * Konfigurationsformular; sobald ein gültiger Pfad angegeben ist, werden die
     * enthaltenen Workspace-Ordner erkannt und je Workspace abgefragt, was
     * passieren soll (eigener Workspace / als ein Kunde / überspringen).
     */
    public function importExport(Request $request): View {
        $this->admin();

        $path = trim((string) $request->query('path', (string) config('plugins.toggl.export_path', '')));
        $workspaces = [];
        if ($path !== '' && is_dir($path)) {
            $reader = new TogglWorkspaceReader;
            foreach (TogglWorkspaceReader::detectWorkspaces($path) as $folder) {
                $dir = rtrim($path, '/') . '/' . $folder;
                $workspaces[] = [
                    'folder' => $folder,
                    'clients' => count($reader->clients($dir)),
                    'projects' => count($reader->projects($dir)),
                    'users' => count($reader->users($dir)),
                ];
            }
        }

        return view('toggl::admin.import-export', [
            'path' => $path,
            'pathValid' => $path !== '' && is_dir($path),
            'workspaces' => $workspaces,
            'summary' => session('toggl_export_summary'),
        ]);
    }

    /** Führt den Workspace-Export-Import aus — als Vorschau (Dry-Run) oder echt. */
    public function runImportExport(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $validated = $request->validate([
            'path' => ['required', 'string'],
            'action' => ['required', 'in:preview,import'],
            'user_mode' => ['required', 'in:per_email,single'],
            'folders' => ['required', 'array', 'min:1'],
            'folders.*' => ['required', 'string'],
            'modes' => ['required', 'array'],
            'modes.*' => ['required', 'in:skip,own,customer'],
            'customer_names' => ['array'],
            'customer_names.*' => ['nullable', 'string', 'max:191'],
        ]);

        abort_unless(is_dir($validated['path']), 422, (string) __('Pfad nicht gefunden.'));

        $workspaceModes = [];
        foreach ($validated['folders'] as $i => $folder) {
            $workspaceModes[$folder] = [
                'mode' => $validated['modes'][$i] ?? TogglExportImporter::MODE_SKIP,
                'customer_name' => $validated['customer_names'][$i] ?? null,
            ];
        }

        $dryRun = $validated['action'] === 'preview';
        $summary = $this->exportImporter->import($validated['path'], $organization, $workspaceModes, $validated['user_mode'], $dryRun);

        return redirect()
            ->route('admin.toggl.import-export', ['path' => $validated['path']])
            ->with('toggl_export_summary', $summary)
            ->with('status', $dryRun
                ? (string) __('Vorschau berechnet — es wurde nichts gespeichert.')
                : (string) __('Import abgeschlossen.'));
    }

    public function dismiss(Request $request): RedirectResponse {
        $admin = $this->admin();

        $validated = $request->validate([
            'client_name' => ['nullable', 'string', 'max:191'],
            'project_name' => ['nullable', 'string', 'max:191'],
        ]);

        $count = $this->service->dismissPending(
            $this->organization($admin),
            $validated['client_name'] ?? null,
            $validated['project_name'] ?? null,
        );

        return back()->with('status', (string) __(':count Eintrag/Einträge verworfen.', ['count' => $count]));
    }

    /** @param array{created: int, skipped: int, unmatched: int} $result */
    private function importMessage(array $result): string {
        return (string) __(':created gebucht, :skipped übersprungen, :unmatched in der Inbox.', $result);
    }

    /**
     * Liefert den zugeordneten Kunden: bestehenden (per Sqid) oder einen neu
     * angelegten (Name aus dem Formular, Default = Toggl-Client-Name).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(Organization $organization, array $data): Customer {
        if (($data['customer_mode'] ?? null) === 'new') {
            return Customer::query()->create([
                'organization_id' => $organization->id,
                'name' => trim((string) $data['new_customer_name']),
                'created_by' => Auth::id(),
            ]);
        }

        $customer = Customer::query()
            ->whereKey($this->decodeId(Customer::class, $data['customer_id'] ?? null))
            ->firstOrFail();
        abort_unless((int) $customer->organization_id === (int) $organization->id, 403);

        return $customer;
    }

    /**
     * Liefert das zugeordnete Projekt unter dem Kunden: bestehendes (per Sqid,
     * muss zum Kunden gehören) oder ein neu angelegtes.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveProject(Organization $organization, Customer $customer, array $data): Project {
        if (($data['project_mode'] ?? null) === 'new') {
            return Project::query()->create([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'name' => trim((string) $data['new_project_name']),
                'status' => ProjectStatus::Active->value,
                'is_default' => false,
                'created_by' => Auth::id(),
            ]);
        }

        $project = Project::query()
            ->whereKey($this->decodeId(Project::class, $data['project_id'] ?? null))
            ->firstOrFail();
        abort_unless((int) $project->organization_id === (int) $organization->id, 403);
        abort_unless((int) $project->customer_id === (int) $customer->id, 422, __('Das gewählte Projekt gehört nicht zum gewählten Kunden.'));

        return $project;
    }

    /** Lädt eine Toggl-Mapping-Reference der Organisation oder bricht mit 404 ab. */
    private function findMapping(Organization $organization, int $id): ExternalReference {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->whereIn('external_type', [TogglImportService::EXT_TYPE_CLIENT, TogglImportService::EXT_TYPE_PROJECT])
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * Dekodiert eine Sqid (oder akzeptiert eine numerische ID) zu einer Model-ID.
     *
     * @param  class-string  $model
     */
    private function decodeId(string $model, mixed $raw): int {
        $id = Sqid::decode($model, (string) $raw);
        if ($id === null && is_numeric($raw)) {
            $id = (int) $raw;
        }
        abort_if($id === null, 422, (string) __('Ungültige Auswahl.'));

        return $id;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Customer>  $customers
     * @return array<int, array{sqid: string, id: int, label: string}>
     */
    private function customerOptions(\Illuminate\Support\Collection $customers): array {
        return $customers->map(fn(Customer $c): array => [
            'sqid' => $c->sqid,
            'id' => (int) $c->id,
            'label' => (string) ($c->company ?: $c->name),
        ])->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Project>  $projects
     * @return array<int, array{sqid: string, customer_id: int, name: string}>
     */
    private function projectOptions(\Illuminate\Support\Collection $projects): array {
        return $projects->map(fn(Project $p): array => [
            'sqid' => $p->sqid,
            'customer_id' => (int) $p->customer_id,
            'name' => (string) $p->name,
        ])->all();
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
