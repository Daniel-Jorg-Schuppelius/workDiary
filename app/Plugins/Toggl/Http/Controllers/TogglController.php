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

use App\Http\Controllers\Controller;
use App\Models\{Customer, ExternalReference, ForeignCustomer, IntegrationInboxItem, Organization, Project};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Toggl\Sources\{ApiWorkspaceSource, TogglApiClient, TogglWorkspaceReader};
use App\Plugins\Toggl\{TogglArchiveException, TogglConfig, TogglExportArchiveService, TogglExportImporter, TogglImportService, TogglOptionBuilder, TogglPlugin};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Admin-Seite für den Toggl-Import: API-Sync auslösen, Detailed-Report-CSV
 * hochladen und die Inbox unzugeordneter Einträge bearbeiten (einem Kunden +
 * Projekt zuweisen oder verwerfen). Zusätzlich der einmalige Workspace-Export-
 * Import ({@see importExport()} / {@see runImportExport()}).
 */
class TogglController extends Controller {
    use ResolvesPluginOrgContext;

    public function __construct(
        private readonly TogglImportService $service,
        private readonly TogglOptionBuilder $options,
        private readonly TogglExportArchiveService $archive,
        private readonly TogglExportImporter $exportImporter = new TogglExportImporter,
    ) {}

    public function index(): View {
        $admin = $this->admin();
        $organization = $admin->organization;

        // Unzugeordnete Toggl-Einträge werden jetzt in der universellen
        // Zuordnungs-Inbox (MVP-103) bearbeitet — hier nur noch die Anzahl
        // offener Gruppen als Hinweis/Deep-Link.
        $inboxOpenCount = $organization instanceof Organization
            ? IntegrationInboxItem::query()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', TogglPlugin::ID)
                ->where('status', IntegrationInboxItem::STATUS_OPEN)
                ->whereNotNull('group_key')
                ->count()
            : 0;

        return view('toggl::admin.import', [
            'inboxOpenCount' => $inboxOpenCount,
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

    /** Mapping-Verwaltung: gemerkte Client-/Projekt-Zuordnungen einsehen/ändern. */
    public function mappings(): View {
        $admin = $this->admin();
        $organization = $admin->organization;

        $mappings = $organization instanceof Organization ? $this->service->mappings($organization) : collect();

        // Benutzer-Zuordnungen vereint aus Primär-Referenzen UND Aliassen —
        // Zweitadressen desselben Benutzers liegen wegen extref_unique im Alias.
        $userMappings = collect();
        if ($organization instanceof Organization) {
            foreach ($mappings->where('external_type', TogglImportService::EXT_TYPE_USER_EMAIL) as $ref) {
                $userMappings->push((object) ['id' => (int) $ref->id, 'source' => 'ref', 'email' => (string) $ref->external_id, 'user' => $ref->referenceable]);
            }
            $aliasRows = \App\Models\ExternalReferenceAlias::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', TogglPlugin::ID)
                ->where('external_type', TogglImportService::EXT_TYPE_USER_EMAIL)
                ->with('referenceable')
                ->get();
            foreach ($aliasRows as $alias) {
                $userMappings->push((object) ['id' => (int) $alias->id, 'source' => 'alias', 'email' => (string) $alias->external_id, 'user' => $alias->referenceable]);
            }
            $userMappings = $userMappings->sortBy('email')->values();
        }

        // Dropdown nur mit UNaufgelösten Toggl-Adressen: Bereits zugeordnete
        // (oder direkt per E-Mail matchende) verschwinden aus der Auswahl.
        $togglEmails = [];
        $allMapped = false;
        if ($organization instanceof Organization) {
            $known = $this->knownTogglEmails($organization);
            $togglEmails = array_values(array_filter(
                $known,
                fn (array $tu): bool => $this->service->resolveImportUser($organization, $tu['email']) === null,
            ));
            $allMapped = $known !== [] && $togglEmails === [];
        }

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'company']);
        // Inkl. kundenloser (interner) Projekte, damit eine Name-Zuordnung auch auf ein
        // unternehmenseigenes Projekt zeigen kann.
        $projects = Project::query()
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id']);
        // Client-Zuordnungen können auch auf Fremdkunden (Endkunden) zeigen.
        $foreignCustomers = ForeignCustomer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id'])
            ->map(fn(ForeignCustomer $fc): array => [
                'sqid' => $fc->sqid,
                'customer_id' => (int) $fc->customer_id,
                'name' => (string) $fc->name,
            ])->all();

        return view('toggl::admin.mappings', [
            'mappings' => $mappings,
            'customers' => $this->options->customerOptions($customers),
            'projects' => $this->options->projectOptions($projects),
            'foreignCustomers' => $foreignCustomers,
            'users' => $this->options->userSelectOptions(),
            'togglEmails' => $togglEmails,
            'allTogglEmailsMapped' => $allMapped,
            'userMappings' => $userMappings,
        ]);
    }

    /**
     * Bekannte Toggl-Benutzer für die Zuordnungs-Auswahl (statt Freitext):
     * Workspace-Benutzer der API (falls Token konfiguriert) plus E-Mails aus
     * offenen Inbox-Snapshots (CSV-Quelle). Dedupe per lower(email).
     *
     * @return array<int, array{email: string, name: string}>
     */
    private function knownTogglEmails(Organization $organization): array {
        $bucket = [];

        $config = TogglConfig::resolve($organization->id);
        if ($config['enabled'] && $config['api_token'] !== null) {
            try {
                $client = new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id']);
                foreach ($client->workspaces() as $workspace) {
                    $this->options->collectTogglUsers($bucket, $client->workspaceUsers((int) $workspace['id']));
                }
            } catch (\Throwable) {
                // API nicht erreichbar → nur Snapshot-Quellen anbieten.
            }
        }

        // E-Mails aus offenen Zeit-Import-Snapshots (deckt reine CSV-Importe ab).
        $snapshots = IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->whereNotNull('group_key')
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('remote_snapshot');
        foreach ($snapshots as $snap) {
            $email = trim((string) (((array) $snap)['user_email'] ?? ''));
            if ($email !== '') {
                $this->options->collectTogglUsers($bucket, [['email' => $email, 'name' => $email]]);
            }
        }

        return $this->options->sortTogglUsers($bucket);
    }

    /**
     * Legt eine Benutzer-Zuordnung an: Toggl-E-Mail → Benutzer der Organisation.
     * Für Mitarbeiter, deren Toggl-Adresse von der workDiary-Adresse abweicht —
     * greift in CSV-/API-Import, Inbox-Buchung und Reparatur-Befehl.
     */
    public function storeUserMapping(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'toggl_email' => ['required', 'email', 'max:191'],
            'user' => ['required', 'string'],
        ]);

        $userId = $this->options->decodeId(\App\Models\User::class, $data['user']);
        $user = \App\Models\User::query()->whereKey($userId)->firstOrFail();
        abort_unless((int) $user->organization_id === (int) $organization->id, 403);

        $this->service->rememberUserEmail($organization, (string) $data['toggl_email'], $user);

        return back()->with('status', (string) __('Benutzer-Zuordnung gespeichert.'));
    }

    /** Zeigt eine Alias-Benutzer-Zuordnung (Zweitadresse) auf einen anderen Benutzer um. */
    public function updateUserAliasMapping(Request $request, int $alias): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $row = $this->findUserAlias($organization, $alias);

        $user = \App\Models\User::query()->whereKey($this->options->decodeId(\App\Models\User::class, $request->input('target_id')))->firstOrFail();
        abort_unless((int) $user->organization_id === (int) $organization->id, 403);

        $row->update([
            'referenceable_type' => $user->getMorphClass(),
            'referenceable_id' => $user->getKey(),
        ]);

        return back()->with('status', (string) __('Zuordnung aktualisiert.'));
    }

    /** Löscht eine Alias-Benutzer-Zuordnung (Zweitadresse). */
    public function deleteUserAliasMapping(int $alias): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $this->findUserAlias($organization, $alias)->delete();

        return back()->with('status', (string) __('Zuordnung entfernt.'));
    }

    private function findUserAlias(Organization $organization, int $alias): \App\Models\ExternalReferenceAlias {
        return \App\Models\ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('external_type', TogglImportService::EXT_TYPE_USER_EMAIL)
            ->whereKey($alias)
            ->firstOrFail();
    }

    /** Zeigt eine gemerkte Zuordnung auf einen anderen Kunden/ein anderes Projekt um. */
    public function updateMapping(Request $request, int $reference): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $ref = $this->findMapping($organization, $reference);

        if ($ref->external_type === TogglImportService::EXT_TYPE_USER_EMAIL) {
            $user = \App\Models\User::query()->whereKey($this->options->decodeId(\App\Models\User::class, $request->input('target_id')))->firstOrFail();
            abort_unless((int) $user->organization_id === (int) $organization->id, 403);
            $ref->update([
                'referenceable_type' => $user->getMorphClass(),
                'referenceable_id' => $user->getKey(),
                'synced_at' => now(),
            ]);

            return back()->with('status', (string) __('Zuordnung aktualisiert.'));
        }

        if ($ref->external_type === TogglImportService::EXT_TYPE_CLIENT) {
            // Client-Ziel kann Kunde oder Fremdkunde (Endkunde) sein — die Sqids
            // sind modell-spezifisch, daher nacheinander dekodieren.
            $raw = (string) $request->input('target_id');
            $foreignId = \App\Support\Sqid::decode(ForeignCustomer::class, $raw);
            $target = $foreignId !== null
                ? ForeignCustomer::query()->whereKey($foreignId)->firstOrFail()
                : Customer::query()->whereKey($this->options->decodeId(Customer::class, $raw))->firstOrFail();
        } else {
            $target = Project::query()->whereKey($this->options->decodeId(Project::class, $request->input('target_id')))->firstOrFail();
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
        $safePath = $this->archive->safeImportPath($path);
        $workspaces = [];
        $togglUsers = [];
        if ($safePath !== null) {
            $reader = new TogglWorkspaceReader;
            foreach (TogglWorkspaceReader::detectWorkspaces($safePath) as $folder) {
                $dir = rtrim($safePath, '/') . '/' . $folder;
                $users = $reader->users($dir);
                $workspaces[] = [
                    'folder' => $folder,
                    'clients' => count($reader->clients($dir)),
                    'projects' => count($reader->projects($dir)),
                    'users' => count($users),
                ];
                $this->options->collectTogglUsers($togglUsers, $users);
            }
        }

        return view('toggl::admin.import-export', [
            'path' => $path,
            'pathValid' => $safePath !== null,
            'workspaces' => $workspaces,
            'summary' => session('toggl_export_summary'),
            'customers' => $this->options->customerSelectOptions(),
            'systemUsers' => $this->options->userSelectOptions(),
            'togglUsers' => $this->options->sortTogglUsers($togglUsers),
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

        $safePath = $this->archive->safeImportPath($validated['path']);
        abort_unless($safePath !== null, 422, (string) __('Pfad nicht gefunden oder nicht erlaubt.'));
        $validated['path'] = $safePath;

        $workspaceModes = [];
        foreach ($validated['folders'] as $i => $folder) {
            $workspaceModes[$folder] = [
                'mode' => $validated['modes'][$i] ?? TogglExportImporter::MODE_SKIP,
                'customer_id' => $this->options->optionalCustomerId($validated['customer_ids'][$i] ?? null),
                'customer_name' => $validated['customer_names'][$i] ?? null,
            ];
        }
        $userMap = $this->options->buildUserMap($validated['user_map'] ?? [], $organization);

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

        try {
            $root = $this->archive->extractUpload((string) $request->file('archive')->getRealPath());
        } catch (TogglArchiveException $e) {
            return back()->withErrors(['archive' => $e->getMessage()]);
        }

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
                $this->options->collectTogglUsers($togglUsers, $users);
            }
        }

        return view('toggl::admin.import-api', [
            'tokenSet' => $tokenSet,
            'workspaces' => $workspaces,
            'summary' => session('toggl_api_summary'),
            'customers' => $this->options->customerSelectOptions(),
            'systemUsers' => $this->options->userSelectOptions(),
            'togglUsers' => $this->options->sortTogglUsers($togglUsers),
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
                'customer_id' => $this->options->optionalCustomerId($validated['customer_ids'][$i] ?? null),
                'customer_name' => $validated['customer_names'][$i] ?? null,
            ];
        }

        abort_if($sources === [], 422, (string) __('Kein Workspace ausgewählt.'));

        $userMap = $this->options->buildUserMap($validated['user_map'] ?? [], $organization);

        $dryRun = $validated['action'] === 'preview';
        $summary = $this->exportImporter->importFromApi($organization, $sources, $workspaceModes, $validated['user_mode'], $dryRun, $userMap);

        return redirect()
            ->route('admin.toggl.import-api')
            ->with('toggl_api_summary', $summary)
            ->with('status', $dryRun
                ? (string) __('Vorschau berechnet — es wurde nichts gespeichert.')
                : (string) __('Import abgeschlossen.'));
    }

    /** @param array{created: int, skipped: int, unmatched: int} $result */
    private function importMessage(array $result): string {
        return (string) __(':created gebucht, :skipped übersprungen, :unmatched in der Inbox.', $result);
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
}
