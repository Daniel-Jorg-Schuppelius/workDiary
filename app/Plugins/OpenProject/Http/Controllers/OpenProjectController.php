<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{ExternalReference, Organization, Project, Task, User};
use App\Plugins\OpenProject\{OpenProjectConfig, OpenProjectPlugin};
use App\Plugins\OpenProject\Services\{OpenProjectExportService, OpenProjectImportService, OpenProjectStructureSync};
use App\Plugins\OpenProject\Sources\OpenProjectApiClient;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin-Seite für die OpenProject-Anbindung: Struktur-Sync + Zeit-Import
 * auslösen, die Inbox unzugeordneter Zeiteinträge bearbeiten, erfasste Zeiten
 * zurückbuchen und die Struktur-Zuordnungen verwalten.
 */
class OpenProjectController extends Controller {
    public function __construct(
        private readonly OpenProjectImportService $import,
        private readonly OpenProjectExportService $export,
        private readonly OpenProjectStructureSync $structure,
    ) {}

    public function index(): View {
        $admin = $this->admin();
        $organization = $admin->organization;

        // Unzugeordnete OpenProject-Einträge werden jetzt in der universellen
        // Zuordnungs-Inbox (MVP-103) bearbeitet — hier nur die offene Anzahl.
        $inboxOpenCount = $organization instanceof Organization
            ? \App\Models\IntegrationInboxItem::query()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', OpenProjectPlugin::ID)
                ->where('status', \App\Models\IntegrationInboxItem::STATUS_OPEN)
                ->whereNotNull('group_key')
                ->count()
            : 0;

        return view('openproject::admin.index', [
            'inboxOpenCount' => $inboxOpenCount,
        ]);
    }

    /** Nur die Struktur (Projekte + Work Packages + Benutzer) neu abgleichen. */
    public function syncStructure(): RedirectResponse {
        $admin = $this->admin();
        $config = OpenProjectConfig::resolve($admin->organization_id);
        if ($config['api_token'] === null || $config['base_url'] === null) {
            return back()->withErrors(['base_url' => __('Keine OpenProject-Zugangsdaten hinterlegt.')]);
        }

        $client = new OpenProjectApiClient($config['api_token'], $config['base_url']);
        $result = $this->import->syncStructure($this->organization($admin), $config, $client);

        return back()->with('status', (string) __('Struktur synchronisiert: :p Projekte, :w Work Packages, :u Benutzer zugeordnet.', [
            'p' => $result['projects']['linked'],
            'w' => $result['work_packages']['linked'],
            'u' => $result['users']['linked'],
        ]));
    }

    /** Vollständiger Lauf: Struktur-Sync + Zeit-Import über das konfigurierte Fenster. */
    public function sync(): RedirectResponse {
        $admin = $this->admin();
        $config = OpenProjectConfig::resolve($admin->organization_id);
        if ($config['api_token'] === null || $config['base_url'] === null) {
            return back()->withErrors(['base_url' => __('Keine OpenProject-Zugangsdaten hinterlegt.')]);
        }

        $to = CarbonImmutable::now();
        $from = $to->subDays(max(1, (int) $config['sync_window_days']));

        $result = $this->import->importFromApi($this->organization($admin), $config, $from, $to);

        return back()->with('status', (string) __(':created gebucht, :skipped übersprungen, :unmatched in der Inbox.', $result));
    }

    /** Rückbuchungs-Seite (Datumsfenster + letzte Zusammenfassung). */
    public function push(): View {
        $this->admin();

        return view('openproject::admin.push', [
            'summary' => session('openproject_push_summary'),
        ]);
    }

    public function runPush(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $config = OpenProjectConfig::resolve($organization->id);

        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $from = ! empty($data['date_from']) ? CarbonImmutable::parse($data['date_from'])->startOfDay() : null;
        $to = ! empty($data['date_to']) ? CarbonImmutable::parse($data['date_to'])->endOfDay() : null;

        $result = $this->export->exportPending($organization, $config, $from, $to);

        return redirect()
            ->route('admin.openproject.push')
            ->with('openproject_push_summary', $result)
            ->with('status', (string) __(':pushed zurückgebucht, :skipped übersprungen, :failed fehlgeschlagen.', [
                'pushed' => $result['pushed'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
            ]));
    }

    /** Mapping-Verwaltung: gemerkte Projekt-/Work-Package-/Benutzer-Zuordnungen. */
    public function mappings(): View {
        $admin = $this->admin();
        $organization = $admin->organization;

        $mappings = $organization instanceof Organization ? $this->structure->mappings($organization) : collect();

        return view('openproject::admin.mappings', [
            'mappings' => $mappings,
            'projects' => $this->projectOptions(),
            'tasks' => $this->taskOptions(),
            'users' => $this->userOptions(),
        ]);
    }

    public function updateMapping(Request $request, int $reference): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $ref = $this->findMapping($organization, $reference);

        $target = match ($ref->external_type) {
            OpenProjectStructureSync::EXT_TYPE_PROJECT => Project::query()->whereKey($this->decodeId(Project::class, $request->input('target_id')))->firstOrFail(),
            OpenProjectStructureSync::EXT_TYPE_WORK_PACKAGE => Task::query()->whereKey($this->decodeId(Task::class, $request->input('target_id')))->firstOrFail(),
            default => User::query()->whereKey($this->decodeId(User::class, $request->input('target_id')))->firstOrFail(),
        };
        abort_unless((int) $target->organization_id === (int) $organization->id, 403);

        $ref->update([
            'referenceable_type' => $target->getMorphClass(),
            'referenceable_id' => $target->getKey(),
            'synced_at' => now(),
        ]);

        return back()->with('status', (string) __('Zuordnung aktualisiert.'));
    }

    public function deleteMapping(int $reference): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $this->findMapping($organization, $reference)->delete();

        return back()->with('status', (string) __('Zuordnung entfernt.'));
    }

    /**
     * Liefert das zugeordnete Projekt: bestehendes (per Sqid) oder ein neu
     * angelegtes (Name aus dem Formular). Nie automatisch anlegen.
     *
     * @param  array<string, mixed>  $data
     */
    private function findMapping(Organization $organization, int $id): ExternalReference {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', OpenProjectPlugin::ID)
            ->whereIn('external_type', [
                OpenProjectStructureSync::EXT_TYPE_PROJECT,
                OpenProjectStructureSync::EXT_TYPE_WORK_PACKAGE,
                OpenProjectStructureSync::EXT_TYPE_USER,
            ])
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
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
     * @return array<int, array{sqid: string, name: string}>
     */
    private function projectOptions(): array {
        return Project::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(Project $p): array => ['sqid' => $p->sqid, 'name' => (string) $p->name])
            ->all();
    }

    /**
     * @return array<int, array{sqid: string, name: string}>
     */
    private function taskOptions(): array {
        return Task::query()
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn(Task $t): array => ['sqid' => $t->sqid, 'name' => (string) $t->title])
            ->all();
    }

    /**
     * @return array<int, array{sqid: string, name: string}>
     */
    private function userOptions(): array {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn(User $u): array => [
                'sqid' => $u->sqid,
                'name' => trim((string) $u->name) !== '' ? $u->name . ' (' . $u->email . ')' : (string) $u->email,
            ])
            ->all();
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
