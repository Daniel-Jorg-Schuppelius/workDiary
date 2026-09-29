<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeImportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Knowledge;

use App\Enums\CloudIntake\CloudIntakeConnectionStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Communication\CommunicationNote;
use App\Models\Knowledge\{ContentCollection, KnowledgeArticle};
use App\Models\Platform\{Organization, User};
use App\Plugins\Contracts\DocumentIntakeSource;
use App\Plugins\PluginManager;
use App\Services\Collections\Import\Contracts\NotebookSource;
use App\Services\Collections\Import\{KnowledgeImportReport, KnowledgeImportService, NotebookSources, ObsidianVaultReader};
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Einbahn-Übernahme aus Obsidian und Notizbuch-Quellen der Plugins wie OneNote
 * (MVP-815, Feature 155; Quellen seit MVP-1042 über {@see NotebookSources}): Dialoge
 * im Einstieg „Wissen“, Lauf auf Anstoß. Übernahmen legen viele Inhalte auf
 * einmal an und nutzen Anbindungen der Organisation — deshalb nur für
 * Administratoren, die zugleich Sammlungen pflegen dürfen.
 */
class KnowledgeImportController extends Controller {
    use ResolvesCurrentOrganization;

    public const SOURCE_OBSIDIAN = 'obsidian';

    public function __construct(private readonly KnowledgeImportService $imports) {}

    public function create(Request $request): View {
        $user = $this->importer();
        $organization = $this->currentOrganization();
        $notebookSource = $this->notebookSource((string) $request->query('source', ''), $organization);

        $notebooks = [];
        $notebookError = false;
        if ($notebookSource !== null) {
            try {
                $notebooks = $notebookSource->notebooks($organization);
            } catch (Throwable) {
                $notebookError = true;
            }
        }

        return view('knowledge-hub._import_dialog', [
            'source' => $notebookSource?->key() ?? self::SOURCE_OBSIDIAN,
            'notebookSource' => $notebookSource,
            'targets' => $this->targets($user),
            'connections' => $notebookSource === null ? $this->cloudConnections($organization) : collect(),
            'notebooks' => $notebooks,
            'notebookError' => $notebookError,
        ]);
    }

    public function storeObsidian(Request $request, ObsidianVaultReader $reader): RedirectResponse {
        $user = $this->importer();
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'connection' => ['required', 'string', 'max:64'],
            'vault_path' => ['nullable', 'string', 'max:500'],
            'target' => ['required', Rule::in($this->targets($user))],
            'title' => ['nullable', 'string', 'max:180'],
        ]);

        $connection = $this->cloudConnections($organization)
            ->firstWhere('id', Sqid::decodeOrNumeric(CloudDocumentConnection::class, (string) $data['connection']));
        abort_if($connection === null, 404);
        $adapter = app(PluginManager::class)->find($connection->provider->pluginId());
        if (! $adapter instanceof DocumentIntakeSource) {
            return back()->with('error', __('collections.import.flash.source_unavailable'));
        }

        $vaultPath = trim((string) ($data['vault_path'] ?? ''));
        $pluginId = $connection->provider->pluginId();
        try {
            $read = $reader->documents($connection, $adapter, $vaultPath, KnowledgeImportService::MAX_DOCUMENTS,
                $this->imports->knownChecker($organization, $pluginId, 'obsidian_note'));
            $report = $this->imports->import($organization, $user, $pluginId, 'obsidian_note',
                $this->rootTitle($data['title'] ?? null, $vaultPath !== '' ? basename($vaultPath) : 'Obsidian'),
                (string) $data['target'], $read['documents'], $read['limited']);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('collections.import.flash.failed'));
        }

        return $this->finished($report);
    }

    public function storeNotebook(Request $request, string $source): RedirectResponse {
        $user = $this->importer();
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'notebook' => ['required', 'string', 'max:512'],
            'target' => ['required', Rule::in($this->targets($user))],
            'title' => ['nullable', 'string', 'max:180'],
        ]);

        $notebookSource = $this->notebookSource($source, $organization);
        if ($notebookSource === null) {
            return back()->with('error', __('collections.import.flash.source_unavailable'));
        }

        try {
            // Notizbuch serverseitig gegen die Liste des Kontos prüfen — keine untergeschobenen IDs.
            $notebook = collect($notebookSource->notebooks($organization))->firstWhere('id', (string) $data['notebook']);
            if (! is_array($notebook)) {
                return back()->with('error', __('collections.import.flash.notebook_invalid'));
            }
            $read = $notebookSource->documents($organization, $notebook['id'], $notebook['name'], KnowledgeImportService::MAX_DOCUMENTS,
                $this->imports->knownChecker($organization, $notebookSource->pluginId(), $notebookSource->referenceType()));
            $report = $this->imports->import($organization, $user, $notebookSource->pluginId(), $notebookSource->referenceType(),
                $this->rootTitle($data['title'] ?? null, $notebook['name']), (string) $data['target'], $read['documents'], $read['limited']);
            $notebookSource->markImported($organization);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('collections.import.flash.failed'));
        }

        return $this->finished($report);
    }

    private function finished(KnowledgeImportReport $report): RedirectResponse {
        $redirect = redirect()
            ->route('knowledge-hub.index', array_filter(['collection' => $report->root?->sqid]))
            ->with('success', __('collections.import.flash.done', $report->counts()));

        return $report->limited ? $redirect->with('warning', __('collections.import.flash.limited', ['max' => KnowledgeImportService::MAX_DOCUMENTS])) : $redirect;
    }

    private function importer(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin() && Gate::allows('create', ContentCollection::class), 403);

        return $user;
    }

    /** @return list<string> */
    private function targets(User $user): array {
        $targets = [];
        if (Gate::forUser($user)->allows('create', CommunicationNote::class)) {
            $targets[] = KnowledgeImportService::TARGET_NOTE;
        }
        if (app(FeatureFlagResolver::class)->isEnabled('module.knowledge') && Gate::forUser($user)->allows('create', KnowledgeArticle::class)) {
            $targets[] = KnowledgeImportService::TARGET_ARTICLE;
        }
        abort_if($targets === [], 403);

        return $targets;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, CloudDocumentConnection> */
    private function cloudConnections(Organization $organization) {
        return CloudDocumentConnection::query()
            ->where('organization_id', $organization->id)
            ->where('status', CloudIntakeConnectionStatus::Active->value)
            ->orderBy('name')
            ->get();
    }

    /** Eingeschaltete und verbundene Notizbuch-Quelle (null = Obsidian bzw. nicht bereit). */
    private function notebookSource(string $key, Organization $organization): ?NotebookSource {
        $source = $key !== self::SOURCE_OBSIDIAN ? app(NotebookSources::class)->get($key) : null;

        return $source !== null && $source->ready($organization) ? $source : null;
    }

    private function rootTitle(?string $title, string $fallback): string {
        $title = trim((string) $title);

        return $title !== '' ? $title : $fallback;
    }
}
