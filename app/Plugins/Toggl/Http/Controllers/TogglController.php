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
use App\Models\{ExternalReference, ExternalReferenceAlias, IntegrationInboxItem, Organization, TimeEntry};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use App\Plugins\Toggl\Services\TogglUserMappingService;
use App\Plugins\Toggl\Sources\{ApiWorkspaceSource, TogglApiClient, TogglWorkspaceReader};
use App\Plugins\Toggl\{TogglArchiveException, TogglConfig, TogglExportArchiveService, TogglExportImporter, TogglExportService, TogglImportService, TogglOptionBuilder, TogglPlugin};
use App\Support\Sqid;
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
        private readonly TogglUserMappingService $mappingService,
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

        $config = TogglConfig::resolve($admin->organization_id);

        // Benutzerzuordnungs-Status (MVP-509): Modus + Ziel des Einbenutzer-
        // Fallbacks sichtbar machen, damit niemand über stille Buchungen rätselt.
        $singleUserMode = (bool) $config['single_user_mode'];
        $defaultUserName = null;
        if ($singleUserMode && $organization instanceof Organization) {
            $defaultUserId = is_numeric($config['default_user_id']) ? (int) $config['default_user_id'] : null;
            $defaultUser = $defaultUserId !== null
                ? \App\Models\User::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereKey($defaultUserId)->first()
                : null;
            $defaultUserName = $defaultUser->name
                ?? $organization->owner->name
                ?? (string) __('Organisations-Owner');
        }

        return view('toggl::admin.import', [
            'inboxOpenCount' => $inboxOpenCount,
            'apiConfigured' => $config['api_token'] !== null,
            'exportEnabled' => (bool) $config['export_enabled'],
            'singleUserMode' => $singleUserMode,
            'defaultUserName' => $defaultUserName,
        ]);
    }

    /** Spiegelung workDiary → Toggl (manueller Lauf; Zeitfenster optional). */
    public function exportApi(Request $request, TogglExportService $export): RedirectResponse {
        $admin = $this->admin();

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $config = TogglConfig::resolve($admin->organization_id);

        $result = $export->exportPending(
            $this->organization($admin),
            $config,
            isset($data['from']) ? CarbonImmutable::parse((string) $data['from'])->startOfDay() : null,
            isset($data['to']) ? CarbonImmutable::parse((string) $data['to'])->endOfDay() : null,
        );

        if ($result['pushed'] === 0 && $result['errors'] !== []) {
            return back()->withErrors(['api' => $result['errors'][0]]);
        }

        $status = __('Toggl-Übertragung: :pushed übertragen, :skipped übersprungen, :failed fehlgeschlagen.', [
            'pushed' => $result['pushed'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
        ]);
        if ($result['errors'] !== []) {
            $status .= ' ' . $result['errors'][0];
        }

        return back()->with('status', $status);
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

    /**
     * Fremdstand eines Konflikt-Items nachladen (Inbox): holt den aktuellen
     * Toggl-Stand des betroffenen Eintrags und legt ihn als Lokal/Remote-Paar
     * am Item ab — Outbox-Fehlschläge speichern sonst keinen Fremdstand, der
     * Konflikt wäre ohne Blick in Toggl nicht entscheidbar.
     */
    public function inspectConflict(IntegrationInboxItem $item): RedirectResponse {
        $admin = $this->admin();
        abort_unless((int) $item->organization_id === (int) $admin->organization_id, 404);
        abort_unless($item->plugin_id === TogglPlugin::ID && $item->case_type === IntegrationInboxItem::CASE_CONFLICT, 404);

        $config = TogglConfig::resolve($admin->organization_id);
        if ($config['api_token'] === null) {
            return back()->withErrors(['api_token' => __('Kein Toggl API-Token hinterlegt.')]);
        }

        $togglId = $this->togglEntryId($item);
        if ($togglId === null) {
            return back()->withErrors(['item' => __('Keine Toggl-Eintrags-ID am Konflikt gefunden.')]);
        }

        $client = TogglApiClient::fromConfig($config);
        $result = $client->fetchEntry($togglId);
        if ($result['status'] === 'error') {
            return back()->withErrors(['item' => __('Fremdstand konnte nicht geladen werden (Toggl nicht erreichbar?).')]);
        }

        // Lokal/Remote-Paar in der Struktur, die die Inbox-Ansicht rendert.
        $snapshot = (array) ($item->remote_snapshot ?? []);
        $entry = $item->referenceable;
        if ($entry instanceof TimeEntry) {
            $snapshot['local'] = [
                'started_at' => $entry->started_at?->toIso8601String(),
                'ended_at' => $entry->ended_at?->toIso8601String(),
                'minutes' => $entry->minutes,
                'description' => $entry->description,
            ];
        }
        $snapshot['remote'] = $result['entry'];
        $snapshot['remote_missing'] = $result['status'] === 'missing';
        $snapshot['inspected_at'] = now()->toIso8601String();
        $item->forceFill(['remote_snapshot' => $snapshot])->save();

        return back()->with('status', $result['status'] === 'missing'
            ? (string) __('Fremdstand geladen: Eintrag existiert in Toggl nicht (mehr).')
            : (string) __('Fremdstand aus Toggl geladen.'));
    }

    /** Numerische Toggl-Eintrags-ID aus external_id bzw. dedupe_key (`toggl:<id>`). */
    private function togglEntryId(IntegrationInboxItem $item): ?int {
        foreach ([(string) $item->external_id, (string) $item->dedupe_key] as $haystack) {
            if (preg_match('/toggl:(\d+)/', $haystack, $m) === 1) {
                return (int) $m[1];
            }
        }

        return null;
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

        return view('toggl::admin.mappings', $this->mappingService->viewData($admin->organization));
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

        $this->mappingService->storeUserMapping($organization, (string) $data['toggl_email'], $data['user']);

        return back()->with('status', (string) __('Benutzer-Zuordnung gespeichert.'));
    }

    /** Zeigt eine Alias-Benutzer-Zuordnung (Zweitadresse) auf einen anderen Benutzer um. */
    public function updateUserAliasMapping(Request $request, string $alias): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $aliasId = Sqid::decodeOrAbort(ExternalReferenceAlias::class, $alias);
        $this->mappingService->updateUserAliasMapping($organization, $aliasId, $request->input('target_id'));

        return back()->with('status', (string) __('Zuordnung aktualisiert.'));
    }

    /** Löscht eine Alias-Benutzer-Zuordnung (Zweitadresse). */
    public function deleteUserAliasMapping(string $alias): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $this->mappingService->deleteUserAliasMapping($organization, Sqid::decodeOrAbort(ExternalReferenceAlias::class, $alias));

        return back()->with('status', (string) __('Zuordnung entfernt.'));
    }

    /** Zeigt eine gemerkte Zuordnung auf einen anderen Kunden/ein anderes Projekt um. */
    public function updateMapping(Request $request, string $reference): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $referenceId = Sqid::decodeOrAbort(ExternalReference::class, $reference);
        $this->mappingService->updateMapping($organization, $referenceId, $request->input('target_id'));

        return back()->with('status', (string) __('Zuordnung aktualisiert.'));
    }

    /** Löscht eine gemerkte Zuordnung (künftige Importe matchen dann nicht mehr automatisch). */
    public function deleteMapping(string $reference): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $this->mappingService->deleteMapping($organization, Sqid::decodeOrAbort(ExternalReference::class, $reference));

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
            'user_mode' => ['required', 'in:per_email,per_email_create,single'],
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
            $client = TogglApiClient::fromConfig($config);
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
            'user_mode' => ['required', 'in:per_email,per_email_create,single'],
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

        $client = TogglApiClient::fromConfig($config);

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

    /** @param array{created: int, skipped: int, unmatched: int, unresolved_users?: int, updated?: int, conflicts?: int, removed?: int, incomplete?: bool} $result */
    private function importMessage(array $result): string {
        $message = (string) __(':created gebucht, :skipped übersprungen, :unmatched in der Inbox.', $result);

        $unresolvedUsers = (int) ($result['unresolved_users'] ?? 0);
        if ($unresolvedUsers > 0) {
            $message .= ' ' . __(':n ohne zuordenbaren Benutzer — Zuordnung unter „Zuordnungen verwalten" pflegen oder in der Integrations-Inbox buchen.', ['n' => $unresolvedUsers]);
        }

        $removed = (int) ($result['removed'] ?? 0);
        if ($removed > 0) {
            $message .= ' ' . __(':removed lokale Einträge entfernt (in Toggl gelöscht).', ['removed' => $removed]);
        }

        if ((bool) ($result['incomplete'] ?? false)) {
            $message .= ' ' . __('Achtung: Der Lauf war unvollständig (Toggl nicht vollständig erreichbar) — Benutzerauflösung und Löschabgleich sind ausgesetzt.');
        }

        return $message;
    }
}
