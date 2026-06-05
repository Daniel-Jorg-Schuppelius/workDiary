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
use App\Plugins\Toggl\Sources\{ApiWorkspaceSource, TogglApiClient, TogglWorkspaceReader};
use App\Plugins\Toggl\{TogglConfig, TogglExportImporter, TogglImportService, TogglPlugin};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\Str;
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
    ) {}

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
        $admin = $this->admin();

        $path = trim((string) $request->query('path', (string) config('plugins.toggl.export_path', '')));
        $workspaces = [];
        $togglUsers = [];
        if ($path !== '' && is_dir($path)) {
            $reader = new TogglWorkspaceReader;
            foreach (TogglWorkspaceReader::detectWorkspaces($path) as $folder) {
                $dir = rtrim($path, '/') . '/' . $folder;
                $users = $reader->users($dir);
                $workspaces[] = [
                    'folder' => $folder,
                    'clients' => count($reader->clients($dir)),
                    'projects' => count($reader->projects($dir)),
                    'users' => count($users),
                ];
                $this->collectTogglUsers($togglUsers, $users);
            }
        }

        return view('toggl::admin.import-export', [
            'path' => $path,
            'pathValid' => $path !== '' && is_dir($path),
            'workspaces' => $workspaces,
            'summary' => session('toggl_export_summary'),
            'customers' => $this->customerSelectOptions(),
            'systemUsers' => $this->userSelectOptions(),
            'togglUsers' => $this->sortTogglUsers($togglUsers),
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
            'customer_ids' => ['array'],
            'customer_ids.*' => ['nullable', 'string', 'max:191'],
            'customer_names' => ['array'],
            'customer_names.*' => ['nullable', 'string', 'max:191'],
            'user_map' => ['array'],
            'user_map.*' => ['nullable', 'string', 'max:191'],
        ]);

        abort_unless(is_dir($validated['path']), 422, (string) __('Pfad nicht gefunden.'));

        $workspaceModes = [];
        foreach ($validated['folders'] as $i => $folder) {
            $workspaceModes[$folder] = [
                'mode' => $validated['modes'][$i] ?? TogglExportImporter::MODE_SKIP,
                'customer_id' => $this->optionalCustomerId($validated['customer_ids'][$i] ?? null),
                'customer_name' => $validated['customer_names'][$i] ?? null,
            ];
        }
        $userMap = $this->buildUserMap($validated['user_map'] ?? [], $organization);

        $dryRun = $validated['action'] === 'preview';
        $summary = $this->exportImporter->import($validated['path'], $organization, $workspaceModes, $validated['user_mode'], $dryRun, $userMap);

        // Persistent (nicht nur Flash), damit die Vorschau-/Ergebnistabelle beim
        // erneuten Aufruf der Seite weiter sichtbar bleibt — bis sie zurückgesetzt wird.
        $request->session()->put('toggl_export_summary', $summary);

        return redirect()
            ->route('admin.toggl.import-export', ['path' => $validated['path']])
            ->with('status', $dryRun
                ? (string) __('Vorschau berechnet — es wurde nichts gespeichert.')
                : (string) __('Import abgeschlossen.'));
    }

    /**
     * Lädt eine Toggl-Export-ZIP hoch, entpackt sie sicher nach
     * storage/app/toggl-imports/<id>/ und leitet auf den bestehenden
     * Pfad-Flow um — der Rest (Workspace-Erkennung, Konfiguration, Import)
     * bleibt identisch zur Server-Pfad-Variante.
     */
    public function uploadExport(Request $request): RedirectResponse {
        $this->admin();

        $request->validate([
            'archive' => ['required', 'file', 'mimes:zip', 'max:204800'], // bis 200 MB
        ]);

        $base = storage_path('app/toggl-imports');
        if (! is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        $this->pruneOldImports($base);

        $target = $base . '/' . now()->format('Ymd_His') . '_' . Str::random(8);
        @mkdir($target, 0775, true);

        $zip = new \ZipArchive;
        if ($zip->open((string) $request->file('archive')->getRealPath()) !== true) {
            $this->rrmdir($target);

            return back()->withErrors(['archive' => (string) __('Keine gültige ZIP-Datei.')]);
        }

        // Zip-Slip-Schutz: keine Pfade mit „..“ oder absoluten Wurzeln zulassen.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                $zip->close();
                $this->rrmdir($target);

                return back()->withErrors(['archive' => (string) __('ZIP konnte nicht entpackt werden.')]);
            }
        }

        $ok = $zip->extractTo($target);
        $zip->close();
        if (! $ok) {
            $this->rrmdir($target);

            return back()->withErrors(['archive' => (string) __('ZIP konnte nicht entpackt werden.')]);
        }

        $root = $this->resolveExportRoot($target);

        return redirect()->route('admin.toggl.import-export', ['path' => $root]);
    }

    /** Verwirft die gespeicherte Vorschau-/Ergebnistabelle. */
    public function resetPreview(Request $request): RedirectResponse {
        $this->admin();
        $request->session()->forget('toggl_export_summary');

        return back()->with('status', (string) __('Vorschau verworfen.'));
    }

    /**
     * Workspace-Import direkt aus der Toggl-API: listet die Workspaces des
     * hinterlegten Tokens und lässt je Workspace festlegen, was passieren soll
     * (eigener Workspace / als ein Kunde / überspringen) — analog zum
     * Ordner-Export, aber ohne Datei-Download.
     */
    public function importApi(): View {
        $admin = $this->admin();

        $config = TogglConfig::resolve($admin->organization_id);
        $tokenSet = $config['api_token'] !== null;
        $workspaces = [];
        $togglUsers = [];

        if ($tokenSet) {
            $client = new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id']);
            foreach ($client->workspaces() as $ws) {
                $source = new ApiWorkspaceSource($client, $ws['id']);
                $users = $source->users();
                $workspaces[] = [
                    'id' => $ws['id'],
                    'name' => $ws['name'],
                    'clients' => count($source->clients()),
                    'projects' => count($source->projects()),
                    'users' => count($users),
                ];
                $this->collectTogglUsers($togglUsers, $users);
            }
        }

        return view('toggl::admin.import-api', [
            'tokenSet' => $tokenSet,
            'workspaces' => $workspaces,
            'summary' => session('toggl_api_summary'),
            'customers' => $this->customerSelectOptions(),
            'systemUsers' => $this->userSelectOptions(),
            'togglUsers' => $this->sortTogglUsers($togglUsers),
        ]);
    }

    /** Führt den API-Workspace-Import aus — als Vorschau (Dry-Run) oder echt. */
    public function runImportApi(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $validated = $request->validate([
            'action' => ['required', 'in:preview,import'],
            'user_mode' => ['required', 'in:per_email,single'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'workspace_ids' => ['required', 'array', 'min:1'],
            'workspace_ids.*' => ['required', 'integer'],
            'workspace_names' => ['array'],
            'workspace_names.*' => ['nullable', 'string', 'max:191'],
            'modes' => ['required', 'array'],
            'modes.*' => ['required', 'in:skip,own,customer'],
            'customer_ids' => ['array'],
            'customer_ids.*' => ['nullable', 'string', 'max:191'],
            'customer_names' => ['array'],
            'customer_names.*' => ['nullable', 'string', 'max:191'],
            'user_map' => ['array'],
            'user_map.*' => ['nullable', 'string', 'max:191'],
        ]);

        $config = TogglConfig::resolve($admin->organization_id);
        if ($config['api_token'] === null) {
            return back()->withErrors(['api_token' => __('Kein Toggl API-Token hinterlegt.')]);
        }

        $from = ! empty($validated['date_from']) ? CarbonImmutable::parse($validated['date_from'])->startOfDay() : null;
        $to = ! empty($validated['date_to']) ? CarbonImmutable::parse($validated['date_to'])->endOfDay() : null;

        $client = new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id']);

        $sources = [];
        $workspaceModes = [];
        foreach ($validated['workspace_ids'] as $i => $wid) {
            $mode = $validated['modes'][$i] ?? TogglExportImporter::MODE_SKIP;
            if ($mode === TogglExportImporter::MODE_SKIP) {
                continue;
            }
            $label = trim((string) ($validated['workspace_names'][$i] ?? ('Workspace ' . $wid))) ?: ('Workspace ' . $wid);
            $sources[$label] = new ApiWorkspaceSource($client, (int) $wid, $from, $to);
            $workspaceModes[$label] = [
                'mode' => $mode,
                'customer_id' => $this->optionalCustomerId($validated['customer_ids'][$i] ?? null),
                'customer_name' => $validated['customer_names'][$i] ?? null,
            ];
        }

        abort_if($sources === [], 422, (string) __('Kein Workspace ausgewählt.'));

        $userMap = $this->buildUserMap($validated['user_map'] ?? [], $organization);

        $dryRun = $validated['action'] === 'preview';
        $summary = $this->exportImporter->importFromApi($organization, $sources, $workspaceModes, $validated['user_mode'], $dryRun, $userMap);

        return redirect()
            ->route('admin.toggl.import-api')
            ->with('toggl_api_summary', $summary)
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
     * Kunden der Organisation als Dropdown-Optionen (für die Workspace-Import-Modi).
     *
     * @return array<int, array{sqid: string, id: int, label: string}>
     */
    private function customerSelectOptions(): array {
        return $this->customerOptions(
            Customer::query()->orderBy('name')->get(['id', 'name', 'company'])
        );
    }

    /**
     * Benutzer der Organisation als Dropdown-Optionen (für die explizite
     * Benutzer-Zuordnung der Toggl-Workspace-Benutzer).
     *
     * @return array<int, array{sqid: string, label: string}>
     */
    private function userSelectOptions(): array {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn(User $u): array => [
                'sqid' => $u->sqid,
                'label' => trim((string) $u->name) !== ''
                    ? $u->name . ' (' . $u->email . ')'
                    : (string) $u->email,
            ])->all();
    }

    /**
     * Sammelt distinkte Toggl-Workspace-Benutzer (über alle Workspaces hinweg,
     * dedupliziert per E-Mail) für die Zuordnungs-Oberfläche.
     *
     * @param  array<string, array{email: string, name: string}>  $bucket  (per Referenz, Schlüssel = lower(email))
     * @param  array<int, array{email: string, name: string, timezone?: ?string}>  $users
     */
    private function collectTogglUsers(array &$bucket, array $users): void {
        foreach ($users as $u) {
            $email = trim($u['email']);
            if ($email === '') {
                continue;
            }
            $key = mb_strtolower($email);
            $bucket[$key] ??= ['email' => $email, 'name' => trim($u['name']) ?: $email];
        }
    }

    /**
     * @param  array<string, array{email: string, name: string}>  $bucket
     * @return array<int, array{email: string, name: string}>
     */
    private function sortTogglUsers(array $bucket): array {
        ksort($bucket);

        return array_values($bucket);
    }

    /** Optionale Kunden-Sqid → ID (null bei leerer Auswahl, z. B. „neuer Kunde"). */
    private function optionalCustomerId(?string $sqid): ?int {
        $sqid = trim((string) $sqid);

        return $sqid === '' ? null : $this->decodeId(Customer::class, $sqid);
    }

    /**
     * Baut die explizite Benutzer-Zuordnung aus der UI (Toggl-E-Mail → System-User).
     * Leere Auswahlen und Benutzer fremder Organisationen werden ignoriert.
     *
     * @param  array<string, string|null>  $raw  E-Mail → User-Sqid
     * @return array<string, int>  lower(email) → User-ID
     */
    private function buildUserMap(array $raw, Organization $organization): array {
        $map = [];
        foreach ($raw as $email => $sqid) {
            $email = trim((string) $email);
            $sqid = trim((string) $sqid);
            if ($email === '' || $sqid === '') {
                continue;
            }
            $userId = Sqid::decode(User::class, $sqid);
            if ($userId === null) {
                continue;
            }
            $user = User::query()->whereKey($userId)->first();
            if ($user instanceof User && (int) $user->organization_id === (int) $organization->id) {
                $map[mb_strtolower($email)] = (int) $user->id;
            }
        }

        return $map;
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

    /**
     * Findet den tatsächlichen Export-Wurzelordner im entpackten ZIP:
     *  - durchläuft transparente „Wrapper"-Ordner (genau ein Unterordner),
     *  - und packt einen flachen Single-Workspace-Export (projects.json direkt
     *    im Ordner, keine Unterordner) in einen benannten Unterordner, damit
     *    {@see TogglWorkspaceReader::detectWorkspaces()} ihn erkennt.
     */
    private function resolveExportRoot(string $dir): string {
        for ($depth = 0; $depth < 6; $depth++) {
            if (TogglWorkspaceReader::detectWorkspaces($dir) !== []) {
                return $dir;
            }

            // Flacher Single-Workspace-Export → in Unterordner „Workspace" heben.
            if (is_file($dir . '/projects.json')) {
                $wrap = $dir . '/Workspace';
                @mkdir($wrap, 0775, true);
                foreach ((array) glob($dir . '/*') as $item) {
                    if ($item === $wrap) {
                        continue;
                    }
                    @rename($item, $wrap . '/' . basename((string) $item));
                }

                return $dir;
            }

            $subdirs = array_values(array_filter((array) glob($dir . '/*', GLOB_ONLYDIR)));
            if (count($subdirs) === 1) {
                $dir = $subdirs[0];

                continue;
            }
            break;
        }

        return $dir;
    }

    /** Entfernt entpackte Import-Ordner, die älter als einen Tag sind (Best-Effort). */
    private function pruneOldImports(string $base): void {
        foreach ((array) glob($base . '/*', GLOB_ONLYDIR) as $dir) {
            if (is_string($dir) && @filemtime($dir) !== false && filemtime($dir) < now()->subDay()->getTimestamp()) {
                $this->rrmdir($dir);
            }
        }
    }

    /** Rekursives Löschen eines Verzeichnisses (Best-Effort). */
    private function rrmdir(string $dir): void {
        if (! is_dir($dir)) {
            return;
        }
        foreach ((array) scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
